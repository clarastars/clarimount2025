<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Exports\AssetsByCategoryExport;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;


class AssetController extends Controller
{
    private function accessibleCompanyIds($user)
    {
        $ids = $user->ownedCompanies()->pluck('id');

        if ($ids->isEmpty()) {
            $employeeCompanyId = Employee::query()
                ->where('user_id', $user->id)
                ->value('company_id');

            if ($employeeCompanyId) {
                $ids = collect([$employeeCompanyId]);
            }
        }

        return $ids;
    }

    private function accessibleCompanies($user)
    {
        return Company::query()
            ->whereIn('id', $this->accessibleCompanyIds($user))
            ->orderBy('name_en')
            ->get();
    }

    private function currentAccessibleCompany($user): ?Company
    {
        $company = $user->currentCompany();
        if ($company) {
            return $company;
        }

        return Company::query()->whereIn('id', $this->accessibleCompanyIds($user))->first();
    }

    /**
     * Display a listing of the assets.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        // Only show assets from companies the user owns
        $query = Asset::with(['category', 'location', 'assignments', 'company', 'assetTemplate'])
            ->whereIn('company_id', $ownedCompanyIds);

        // Handle search with improved relevance
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                // Primary search fields (highest priority)
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
                
                // Secondary search fields
                $q->orWhere('service_tag_number', 'like', "%{$search}%")
                  ->orWhere('finance_tag_number', 'like', "%{$search}%")
                  ->orWhere('model_name', 'like', "%{$search}%")
                  ->orWhere('model_number', 'like', "%{$search}%");
            });
            
            // Also search through related data
            $query->orWhereHas('category', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
            
            $query->orWhereHas('company', function($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
            
            $query->orWhereHas('location', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Handle category filter
        if ($categoryId = $request->get('category_id')) {
            $query->where('asset_category_id', $categoryId);
        }

        // Handle location filter
        if ($locationId = $request->get('location_id')) {
            $query->where('location_id', $locationId);
        }

        $assets = $query->orderBy('asset_tag')->paginate(25)->withQueryString();

        // Get categories and locations for filters (from all owned companies)
        $categories = AssetCategory::withDepth()
            ->orderBy('_lft')
            ->get();

        $locations = Location::orderBy('name')
            ->get();

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'categories' => $categories,
            'locations' => $locations,
            'filters' => $request->only(['search', 'category_id', 'location_id']),
        ]);
    }

    /**
     * Show the form for creating a new asset.
     */
    public function create(): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $this->accessibleCompanies($user);

        // If user doesn't have any companies, redirect to create one
        if ($companies->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        $currentCompany = $this->currentAccessibleCompany($user);

        // Get categories and locations for current company (for initial load)
        $categories = $currentCompany ?
            AssetCategory::scoped(['company_id' => $currentCompany->id])
                ->withDepth()
                ->orderBy('_lft')
                ->get() : collect();

        $locations = $currentCompany ?
            Location::where('company_id', $currentCompany->id)
                ->orderBy('name')
                ->get() : collect();

        return Inertia::render('Assets/Create', [
            'companies' => $companies,
            'currentCompany' => $currentCompany,
            'categories' => $categories,
            'locations' => $locations,
        ]);
    }

    /**
     * Store a newly created asset in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $company = $this->currentAccessibleCompany($user);

        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        $validated = $request->validate([
            'asset_template_id' => 'required|exists:asset_templates,id',
            'location_id' => 'nullable|exists:locations,id',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'assigned_to' => 'nullable|exists:employees,id',
            'serial_number' => 'nullable|string|max:255',
            'condition' => 'required|in:good,damaged',
            'image' => 'nullable|image|max:5120', // 5MB max
            'quantity' => 'nullable|integer|min:1|max:100', // Allow bulk creation up to 100 assets

            // Workstation range fields
            'creation_mode' => 'required|in:single,bulk,workstation_range',
            'workstation_prefix' => 'nullable|string|max:100',
            'workstation_start' => 'nullable|integer|min:1|max:999',
            'workstation_end' => 'nullable|integer|min:1|max:999',
            'workstation_company_id' => 'nullable|exists:companies,id',

            // Print station fields
            'send_to_print_station' => 'nullable|boolean',
            'print_priority' => 'nullable|in:low,normal,high,urgent',
            'print_comment' => 'nullable|string|max:500',
        ]);

        // Get the asset template and validate access
        $template = \App\Models\AssetTemplate::find($validated['asset_template_id']);
        if (!$template) {
            return back()->withErrors(['asset_template_id' => 'Invalid asset template.']);
        }

        // Check if user has access to this template (either global or belongs to their company)
//        if (!$template->is_global && $template->company_id !== $company->id) {
//            return back()->withErrors(['asset_template_id' => 'Invalid asset template.']);
//        }

        // Ensure template has a valid category
        if (!$template->asset_category_id) {
            return back()->withErrors(['asset_template_id' => 'Selected template does not have a category assigned. Please update the template first.']);
        }

        // Determine the company to use (from form or current company)
        $targetCompanyId = $validated['company_id'] ?? $company->id;

        // Verify user owns the target company
        if (! $this->accessibleCompanyIds($user)->contains((int) $targetCompanyId)) {
            return back()->withErrors(['company_id' => 'Invalid company selection.']);
        }

        // Handle different creation modes
        $creationMode = $validated['creation_mode'] ?? 'single';

        // Validate based on creation mode
        if ($creationMode === 'workstation_range') {
            // Validate workstation range fields
            if (empty($validated['workstation_prefix'])) {
                return back()->withErrors(['workstation_prefix' => 'Workstation prefix is required for workstation range creation.']);
            }
            if (empty($validated['workstation_start']) || empty($validated['workstation_end'])) {
                return back()->withErrors(['workstation_start' => 'Start and end numbers are required for workstation range creation.']);
            }
            if ($validated['workstation_start'] > $validated['workstation_end']) {
                return back()->withErrors(['workstation_start' => 'Start number must be less than or equal to end number.']);
            }
            if (($validated['workstation_end'] - $validated['workstation_start'] + 1) > 100) {
                return back()->withErrors(['workstation_end' => 'Maximum 100 workstations can be created at once.']);
            }

            // Use workstation company if specified
            if (!empty($validated['workstation_company_id'])) {
                if (! $this->accessibleCompanyIds($user)->contains((int) $validated['workstation_company_id'])) {
                    return back()->withErrors(['workstation_company_id' => 'Invalid workstation company selection.']);
                }
                $workstationCompanyId = (int) $validated['workstation_company_id'];
            } else {
                $workstationCompanyId = $targetCompanyId;
            }
        } else {
            // For single/bulk creation, location is required
            if (empty($validated['location_id'])) {
                return back()->withErrors(['location_id' => 'Location is required for single/bulk creation.']);
            }

            $location = Location::find($validated['location_id']);
            if (!$location) {
                return back()->withErrors(['location_id' => 'Invalid location selection.']);
            }
        }

        // Validate department exists (if provided) - no company restriction
        if (!empty($validated['department_id'])) {
            $department = \App\Models\Department::find($validated['department_id']);
            if (!$department) {
                return back()->withErrors(['department_id' => 'Invalid department selection.']);
            }
        }

        // Validate employee exists (if provided) - no company restriction
        if (!empty($validated['assigned_to'])) {
            $employee = \App\Models\Employee::find($validated['assigned_to']);
            if (!$employee) {
                return back()->withErrors(['assigned_to' => 'Invalid employee selection.']);
            }
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        // Create assets based on creation mode
        $createdAssets = [];

        if ($creationMode === 'workstation_range') {
            // Create assets for workstation range
            $prefix = $validated['workstation_prefix'];
            $start = $validated['workstation_start'];
            $end = $validated['workstation_end'];

            for ($i = $start; $i <= $end; $i++) {
                $workstationName = $prefix . $i;

                // Create or find the workstation location
                $workstationLocation = $this->createOrFindWorkstationLocation(
                    $workstationName,
                    $workstationCompanyId,
                    $prefix,
                    $i
                );

                if (!$workstationLocation) {
                    continue; // Skip if location creation failed
                }

                // Create asset with data from template
                $assetData = [
                    'company_id' => $targetCompanyId,
                    'asset_category_id' => $template->asset_category_id,
                    'location_id' => $workstationLocation->id,
                    'assigned_to' => $validated['assigned_to'],
                    'serial_number' => $validated['serial_number'],
                    'condition' => $validated['condition'],
                    'model_name' => $template->model_name,
                    'model_number' => $template->model_number,
                    'manufacturer' => $template->manufacturer,
                    'notes' => $template->default_notes,
                    'image_path' => $imagePath,
                    'asset_template_id' => $template->id,
                    'status' => !empty($validated['assigned_to']) ? 'assigned' : 'available',
                    'assigned_date' => !empty($validated['assigned_to']) ? now() : null,
                ];

                // Generate unique asset tag for each asset
                $assetData['asset_tag'] = Asset::generateUniqueAssetTag($targetCompanyId);

                $asset = Asset::create($assetData);
                $createdAssets[] = $asset;
            }

            $totalCreated = count($createdAssets);
            $message = "Successfully created {$totalCreated} assets for workstations {$prefix}{$start} to {$prefix}{$end}.";
        } else {
            // Single or bulk creation for one location
            $quantity = $validated['quantity'] ?? 1;

            for ($i = 0; $i < $quantity; $i++) {
                // Create asset with data from template
                $assetData = [
                    'company_id' => $targetCompanyId,
                    'asset_category_id' => $template->asset_category_id,
                    'location_id' => $validated['location_id'],
                    'assigned_to' => $validated['assigned_to'],
                    'serial_number' => $validated['serial_number'],
                    'condition' => $validated['condition'],
                    'model_name' => $template->model_name,
                    'model_number' => $template->model_number,
                    'manufacturer' => $template->manufacturer,
                    'notes' => $template->default_notes,
                    'image_path' => $imagePath,
                    'asset_template_id' => $template->id,
                    'status' => !empty($validated['assigned_to']) ? 'assigned' : 'available',
                    'assigned_date' => !empty($validated['assigned_to']) ? now() : null,
                ];

                // Generate unique asset tag for each asset
                $assetData['asset_tag'] = Asset::generateUniqueAssetTag($targetCompanyId);

                $asset = Asset::create($assetData);
                $createdAssets[] = $asset;
            }

            $message = $quantity > 1
                ? "Successfully created {$quantity} assets."
                : 'Asset created successfully.';
        }

        // Increment template usage count by number of assets created
        $template->increment('usage_count', count($createdAssets));

        // Create print jobs if requested
        if ($validated['send_to_print_station'] ?? false) {
            $this->createPrintJobs($createdAssets, $validated, $user);
        }

        // Store created asset IDs in session for bulk printing
        session(['bulk_created_assets' => collect($createdAssets)->pluck('id')->toArray()]);

        return redirect()->route('assets.index')
            ->with('success', $message)
            ->with('show_bulk_print', count($createdAssets) > 1);
    }



    /**
     * Display the specified asset.
     */
    public function show(Asset $asset): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        // Check if user has access to this asset (asset must belong to one of their companies)
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        $asset->load(['category', 'location', 'assignments.employee', 'company', 'assetTemplate']);

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
        ]);
    }

    /**
     * Show the form for editing the specified asset.
     */
    public function edit(Asset $asset): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        // Check if user has access to this asset (asset must belong to one of their companies)
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        // Get categories and locations from all owned companies for editing flexibility
        $categories = AssetCategory::withDepth()
            ->orderBy('_lft')
            ->get();

        $locations = Location::orderBy('name')
            ->get();

        // Get user's owned companies for company selection
        $companies = $this->accessibleCompanies($user);

        return Inertia::render('Assets/Edit', [
            'asset' => $asset,
            'categories' => $categories,
            'locations' => $locations,
            'companies' => $companies,
        ]);
    }

    /**
     * Update the specified asset in storage.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        // Check if user has access to this asset (asset must belong to one of their companies)
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'serial_number' => 'nullable|string|max:255',
            'service_tag_number' => 'nullable|string|max:255',
            'finance_tag_number' => 'nullable|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'company_id' => 'required|exists:companies,id',
            'model_name' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'condition' => 'required|in:good,damaged',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'remove_image' => 'boolean',
        ]);

        // Validate that the selected company is owned by the user
        if (!$ownedCompanyIds->contains($validated['company_id'])) {
            return back()->withErrors(['company_id' => 'You can only assign assets to companies you own.']);
        }

        // Just validate that category and location exist (no company restrictions)
        $category = AssetCategory::find($validated['asset_category_id']);
        $location = Location::find($validated['location_id']);

        if (!$category) {
            return back()->withErrors(['asset_category_id' => 'Invalid asset category.']);
        }

        if (!$location) {
            return back()->withErrors(['location_id' => 'Invalid location.']);
        }

        // Handle image upload/removal
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($asset->image_path && Storage::disk('public')->exists($asset->image_path)) {
                Storage::disk('public')->delete($asset->image_path);
            }

            // Store new image
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        } elseif ($request->boolean('remove_image') && $asset->image_path) {
            // Remove current image
            if (Storage::disk('public')->exists($asset->image_path)) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $validated['image_path'] = null;
        }

        // Remove non-model fields from validated data
        unset($validated['image'], $validated['remove_image']);

        $asset->update($validated);

        return redirect()->route('assets.show', $asset)
            ->with('success', 'Asset updated successfully.');
    }

    /**
     * Create or find a workstation location.
     */
    private function createOrFindWorkstationLocation($workstationName, $companyId, $prefix, $number)
    {
        // First try to find existing location
        $existingLocation = Location::where('company_id', $companyId)
            ->where('name', $workstationName)
            ->first();

        if ($existingLocation) {
            return $existingLocation;
        }

        // Create new workstation location
        try {
            $location = Location::create([
                'company_id' => $companyId,
                'name' => $workstationName,
                'building' => null,
                'office_number' => (string) $number,
                'address' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'country' => null,
                'is_active' => true,
            ]);

            return $location;
        } catch (\Exception $e) {
            \Log::error("Failed to create workstation location: {$workstationName}", [
                'error' => $e->getMessage(),
                'company_id' => $companyId
            ]);
            return null;
        }
    }

    /**
     * Get bulk created assets for printing.
     */
    public function getBulkCreatedAssets(Request $request)
    {
        $user = Auth::user();
        $assetIds = session('bulk_created_assets', []);

        if (empty($assetIds)) {
            return response()->json(['error' => 'No bulk created assets found'], 404);
        }

        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        $assets = Asset::whereIn('id', $assetIds)
            ->whereIn('company_id', $ownedCompanyIds)
            ->with(['company'])
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'serial_number' => $asset->serial_number,
                    'company_name' => $asset->company->name_en ?? 'Unknown Company',
                ];
            });

        return response()->json($assets);
    }

    /**
     * Clear bulk created assets from session.
     */
    public function clearBulkCreatedAssets(Request $request)
    {
        session()->forget('bulk_created_assets');
        return response()->json(['success' => true]);
    }

    /**
     * Create print jobs for the created assets.
     */
    private function createPrintJobs(array $createdAssets, array $validated, $user)
    {
        foreach ($createdAssets as $asset) {
            // Load relationships needed for print data
            $asset->load(['company', 'category', 'location']);

            // Prepare print data
            $printData = [
                'asset_tag' => $asset->asset_tag,
                'serial_number' => $asset->serial_number,
                'model_name' => $asset->model_name,
                'model_number' => $asset->model_number,
                'category_name' => $asset->category->name ?? null,
                'location_name' => $asset->location->name ?? null,
                'company_name' => $asset->company->name_en,
                'manufacturer' => $asset->manufacturer,
                'condition' => $asset->condition,
                'status' => $asset->status,
            ];

            // Create the print job
            \App\Models\PrintJob::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'company_id' => $asset->company_id,
                'priority' => $validated['print_priority'] ?? 'normal',
                'printer_name' => null, // Will be set by print station
                'comment' => $validated['print_comment'] ?? null,
                'print_data' => $printData,
            ]);
        }
    }

    /**
     * Remove the specified asset from storage.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage assets.');
        }

        // Check if user has access to this asset (asset must belong to one of their companies)
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        // Check if asset is currently assigned
        if ($asset->assignments()->active()->count() > 0) {
            return redirect()->route('assets.show', $asset)
                ->with('error', 'Cannot delete asset that is currently assigned. Please return the asset first.');
        }

        $asset->delete();

        return redirect()->route('assets.index')
            ->with('success', 'Asset deleted successfully.');
    }

    /**
     * Get assignment history for an asset.
     */
    public function getAssignments(Asset $asset)
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // Check if user has access to this asset
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        $assignments = $asset->assignments()
            ->with(['employee', 'assignedBy', 'returnedBy'])
            ->orderBy('assigned_date', 'desc')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'asset_id' => $assignment->asset_id,
                    'employee_id' => $assignment->employee_id,
                    'assigned_by' => $assignment->assigned_by,
                    'assigned_date' => $assignment->assigned_date,
                    'returned_date' => $assignment->returned_date,
                    'returned_by' => $assignment->returned_by,
                    'status' => $assignment->status,
                    'assignment_notes' => $assignment->assignment_notes,
                    'return_notes' => $assignment->return_notes,
                    'condition_notes' => $assignment->condition_notes,
                    'assigned_to_name' => $assignment->employee ? $assignment->employee->full_name : 'Unknown',
                    'assigned_by_name' => $assignment->assignedBy ? $assignment->assignedBy->name : 'Unknown',
                    'returned_by_name' => $assignment->returnedBy ? $assignment->returnedBy->name : null,
                    'assigned_at' => $assignment->assigned_date,
                    'returned_at' => $assignment->returned_date,
                    'duration_days' => $assignment->duration_days,
                    'has_assignment_document' => $this->hasSignedDocument($assignment->id, 'assignment'),
                    'has_return_document' => $this->hasSignedDocument($assignment->id, 'return'),
                ];
            });

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Generate and return a printable assignment document.
     */
    public function printAssignmentDocument(Asset $asset, \App\Models\AssetAssignment $assignment)
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // Check if user has access to this asset
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        // Load relationships
        $assignment->load(['employee', 'assignedBy', 'returnedBy']);
        $asset->load(['company', 'category', 'location']);

        $type = request('type', 'assignment');

        $htmlContent = $this->generateAssignmentDocumentHTML($asset, $assignment, $type);

        return response($htmlContent, 200)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="' . $type . '_document_' . $asset->asset_tag . '_' . $assignment->id . '.html"');
    }

    /**
     * Upload a signed assignment document.
     */
    public function uploadSignedDocument(Asset $asset, \App\Models\AssetAssignment $assignment, Request $request)
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // Check if user has access to this asset
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,gif|max:5120', // 5MB max
            'type' => 'required|in:assignment,return',
            'assignment_id' => 'required|exists:asset_assignments,id',
        ]);

        $type = $validated['type'];
        $file = $request->file('document');

        // Generate unique filename
        $filename = $type . '_document_' . $asset->asset_tag . '_' . $assignment->id . '.' . $file->getClientOriginalExtension();

        // Store the file
        $path = $file->storeAs('assignment_documents', $filename, 'public');

        // Update the assignment record to track the document
        $assignment->update([
            $type . '_document_path' => $path,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'path' => $path,
        ]);
    }

    /**
     * Download a signed assignment document.
     */
    public function downloadSignedDocument(Asset $asset, \App\Models\AssetAssignment $assignment)
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        // Check if user has access to this asset
        if (!$ownedCompanyIds->contains($asset->company_id)) {
            abort(403);
        }

        $type = request('type', 'assignment');
        $documentPath = $assignment->{$type . '_document_path'};

        if (!$documentPath || !Storage::disk('public')->exists($documentPath)) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $fullPath = Storage::disk('public')->path($documentPath);
        $filename = basename($documentPath);

        return response()->download($fullPath, $filename);
    }

    /**
     * Check if a signed document exists for an assignment.
     */
    private function hasSignedDocument(int $assignmentId, string $type): bool
    {
        $assignment = \App\Models\AssetAssignment::find($assignmentId);
        if (!$assignment) {
            return false;
        }

        $documentPath = $assignment->{$type . '_document_path'};

        return $documentPath && Storage::disk('public')->exists($documentPath);
    }

    /**
     * Generate HTML content for assignment document.
     */
    private function generateAssignmentDocumentHTML(Asset $asset, \App\Models\AssetAssignment $assignment, string $type): string
    {
        $isAssignment = $type === 'assignment';
        $title = $isAssignment ? 'Asset Assignment Document' : 'Asset Return Document';

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info {
            margin-bottom: 30px;
        }
        .asset-info, .employee-info, .assignment-info {
            margin-bottom: 30px;
        }
        .info-section {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 40%;
        }
        .info-value {
            width: 60%;
        }
        .signature-section {
            border: 2px solid #333;
            padding: 20px;
            margin-top: 40px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .signature-box {
            width: 45%;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            text-align: center;
        }
        .signature-space {
            height: 60px;
        }
        .print-date {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-top: 20px;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $title . '</h1>
        <p><strong>Document ID:</strong> ' . strtoupper($type) . '-' . $asset->asset_tag . '-' . $assignment->id . '</p>
    </div>

    <div class="company-info info-section">
        <h3>Company Information</h3>
        <div class="info-row">
            <span class="info-label">Company Name:</span>
            <span class="info-value">' . ($asset->company->name_en ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Document Date:</span>
            <span class="info-value">' . now()->format('Y-m-d H:i:s') . '</span>
        </div>
    </div>

    <div class="asset-info info-section">
        <h3>Asset Information</h3>
        <div class="info-row">
            <span class="info-label">Asset Tag:</span>
            <span class="info-value">' . $asset->asset_tag . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Serial Number:</span>
            <span class="info-value">' . ($asset->serial_number ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Model:</span>
            <span class="info-value">' . ($asset->model_name ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Category:</span>
            <span class="info-value">' . ($asset->category->name ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Location:</span>
            <span class="info-value">' . ($asset->location->name ?? 'N/A') . '</span>
        </div>
    </div>

    <div class="employee-info info-section">
        <h3>Employee Information</h3>
        <div class="info-row">
            <span class="info-label">Employee Name:</span>
            <span class="info-value">' . ($assignment->employee->full_name ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Employee ID:</span>
            <span class="info-value">' . ($assignment->employee->employee_id ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Department:</span>
            <span class="info-value">' . ($assignment->employee->department ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value">' . ($assignment->employee->work_email ?? $assignment->employee->personal_email ?? 'N/A') . '</span>
        </div>
    </div>

    <div class="assignment-info info-section">
        <h3>' . ($isAssignment ? 'Assignment' : 'Return') . ' Details</h3>
        <div class="info-row">
            <span class="info-label">' . ($isAssignment ? 'Assignment' : 'Return') . ' Date:</span>
            <span class="info-value">' . ($isAssignment ? $assignment->assigned_date->format('Y-m-d') : ($assignment->returned_date ? $assignment->returned_date->format('Y-m-d') : 'N/A')) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">' . ($isAssignment ? 'Assigned' : 'Returned') . ' By:</span>
            <span class="info-value">' . ($isAssignment ? ($assignment->assignedBy->name ?? 'N/A') : ($assignment->returnedBy->name ?? 'N/A')) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value">' . ucfirst($assignment->status) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Notes:</span>
            <span class="info-value">' . ($isAssignment ? ($assignment->assignment_notes ?? 'N/A') : ($assignment->return_notes ?? 'N/A')) . '</span>
        </div>
    </div>

    <div class="signature-section">
        <h3>' . ($isAssignment ? 'Assignment' : 'Return') . ' Acknowledgment</h3>
        <p>By signing below, I acknowledge that I have ' . ($isAssignment ? 'received' : 'returned') . ' the above-mentioned asset and agree to the terms and conditions of this ' . ($isAssignment ? 'assignment' : 'return') . '.</p>

        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-space"></div>
                <div><strong>Employee Signature</strong></div>
                <div>' . ($assignment->employee->full_name ?? 'N/A') . '</div>
            </div>
            <div class="signature-box">
                <div class="signature-space"></div>
                <div><strong>IT Department Signature</strong></div>
                <div>' . ($isAssignment ? ($assignment->assignedBy->name ?? 'N/A') : ($assignment->returnedBy->name ?? 'N/A')) . '</div>
            </div>
        </div>

        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-space"></div>
                <div><strong>Date</strong></div>
                <div>____________________</div>
            </div>
            <div class="signature-box">
                <div class="signature-space"></div>
                <div><strong>Date</strong></div>
                <div>____________________</div>
            </div>
        </div>
    </div>

    <div class="print-date">
        Generated on: ' . now()->format('Y-m-d H:i:s') . '
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Export all assets to Excel with individual rows
     */
    public function exportByCategory(Request $request)
    {
        $user = Auth::user();
        $ownedCompanyIds = $this->accessibleCompanyIds($user);

        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('assets.index')
                ->with('error', 'You must own at least one company to export assets.');
        }

        // Get all assets from user's companies with relationships
        $assets = Asset::with(['category', 'company', 'location'])
            ->whereIn('company_id', $ownedCompanyIds)
            ->get();

        // Sort by category name first, then company name
        $sortedAssets = $assets->sortBy(function ($asset) {
            $categoryName = $asset->category->name ?? 'ZZZ';
            $companyName = $asset->company->name_en ?? 'ZZZ';
            // Pad category name to ensure proper sorting
            return str_pad($categoryName, 100, ' ', STR_PAD_RIGHT) . $companyName;
        })->values();

        $filename = 'assets_export_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new AssetsByCategoryExport($sortedAssets), $filename);
    }
}
