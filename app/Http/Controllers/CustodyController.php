<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\CustodyChange;
use App\Models\Employee;
use App\Services\CustodyDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CustodyController extends Controller
{
    use AuthorizesEmployeeAccess;

    /**
     * Show the custody management interface for an employee.
     */
    public function show(Employee $employee): Response
    {
        $user = Auth::user();
        $this->abortUnlessCanUpdateEmployeeCustody($user, $employee);

        $companyIds = $this->employeeQueryableCompanyIds($user);

        // Load current assets with their categories
        $currentAssets = $employee->assets()
            ->with(['assetCategory', 'location', 'company'])
            ->where('status', 'assigned')
            ->get();

        // Get available assets for assignment (available assets from user's companies)
        $availableAssets = Asset::whereIn('company_id', $companyIds->isEmpty() ? [-1] : $companyIds)
            ->where('status', 'available')
            ->with(['assetCategory', 'location', 'company', 'assetTemplate'])
            ->get();

        // Get recent custody changes for this employee
        $recentCustodyChanges = CustodyChange::where('employee_id', $employee->id)
            ->with(['updatedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Employees/CustodyManagement', [
            'employee' => $employee->load(['company', 'nationality', 'residenceCountry']),
            'currentAssets' => $currentAssets,
            'availableAssets' => $availableAssets,
            'recentCustodyChanges' => $recentCustodyChanges,
        ]);
    }

    /**
     * Store a new custody change.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $user = Auth::user();
        if (! $this->canUpdateEmployeeCustody($user) || ! $this->canAccessEmployee($user, $employee)) {
            return response()->json(['error' => 'Unauthorized access to this employee.'], 403);
        }

        $ownedCompanyIds = $this->employeeQueryableCompanyIds($user);

        $validated = $request->validate([
            'new_asset_ids' => 'present|array',
            'new_asset_ids.*' => 'exists:assets,id',
            'changes_summary' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Get current asset state
            $currentAssets = $employee->assets()
                ->with(['assetCategory', 'location', 'company'])
                ->where('status', 'assigned')
                ->get();

            // Get new assets
            $newAssets = !empty($validated['new_asset_ids']) 
                ? Asset::whereIn('id', $validated['new_asset_ids'])
                    ->whereIn('company_id', $ownedCompanyIds)
                    ->with(['assetCategory', 'location', 'company'])
                    ->get()
                : collect(); // Empty collection when no assets selected

            // Get IDs of currently assigned assets
            $currentAssetIds = $currentAssets->pluck('id')->toArray();
            
            // Only validate availability for assets that are truly new (not currently assigned to this employee)
            $assetsToAdd = $newAssets->filter(function ($asset) use ($currentAssetIds) {
                return !in_array($asset->id, $currentAssetIds);
            });
            
            // Validate that new assets are available
            foreach ($assetsToAdd as $asset) {
                if ($asset->status !== 'available') {
                    return response()->json([
                        'error' => "Asset {$asset->asset_tag} is not available for assignment."
                    ], 422);
                }
            }

            // Prepare state data
            $previousState = [
                'assets' => $currentAssets->map(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'asset_tag' => $asset->asset_tag,
                        'model_name' => $asset->model_name,
                        'model_number' => $asset->model_number,
                        'serial_number' => $asset->serial_number,
                        'category_name' => $asset->assetCategory->name ?? null,
                        'location_name' => $asset->location->name ?? null,
                        'status' => $asset->status,
                        'condition' => $asset->condition,
                    ];
                })->toArray(),
                'count' => $currentAssets->count(),
            ];

            $newState = [
                'assets' => $newAssets->map(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'asset_tag' => $asset->asset_tag,
                        'model_name' => $asset->model_name,
                        'model_number' => $asset->model_number,
                        'serial_number' => $asset->serial_number,
                        'category_name' => $asset->assetCategory->name ?? null,
                        'location_name' => $asset->location->name ?? null,
                        'status' => 'assigned',
                        'condition' => $asset->condition,
                    ];
                })->toArray(),
                'count' => $newAssets->count(),
            ];

            // Create custody change record
            $custodyChange = CustodyChange::create([
                'employee_id' => $employee->id,
                'updated_by' => $user->id,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'changes_summary' => $validated['changes_summary'],
                'document_path' => null, // Document will be uploaded later
                'status' => 'pending',
            ]);

            // Update asset assignments
            // Get IDs of new assets
            $newAssetIds = $newAssets->pluck('id')->toArray();
            
            // Find assets to return (in current but not in new)
            $assetsToReturn = $currentAssets->filter(function ($asset) use ($newAssetIds) {
                return !in_array($asset->id, $newAssetIds);
            });
            
            // Find assets to add (in new but not in current)
            $assetsToAdd = $newAssets->filter(function ($asset) use ($currentAssetIds) {
                return !in_array($asset->id, $currentAssetIds);
            });
            
            // Return assets that were removed
            foreach ($assetsToReturn as $asset) {
                // Get the current active assignment
                $activeAssignment = AssetAssignment::where('asset_id', $asset->id)
                    ->where('employee_id', $employee->id)
                    ->where('status', 'active')
                    ->first();
                
                if ($activeAssignment) {
                    // Update existing assignment to returned
                    $activeAssignment->update([
                        'returned_date' => now(),
                        'returned_by' => $user->id,
                        'status' => 'returned',
                        'return_notes' => 'Returned due to custody change',
                        'custody_change_id' => $custodyChange->id,
                    ]);
                } else {
                    // Create return assignment record if no active assignment found
                    AssetAssignment::create([
                        'asset_id' => $asset->id,
                        'employee_id' => $employee->id,
                        'assigned_by' => $user->id,
                        'assigned_date' => $asset->assigned_date ?? now(),
                        'returned_date' => now(),
                        'returned_by' => $user->id,
                        'status' => 'returned',
                        'return_notes' => 'Returned due to custody change',
                        'custody_change_id' => $custodyChange->id,
                    ]);
                }

                // Update asset status
                $asset->update([
                    'assigned_to' => null,
                    'assigned_date' => null,
                    'status' => 'available',
                ]);
            }

            // Add new assets (only those not currently assigned)
            foreach ($assetsToAdd as $asset) {
                // Create assignment record
                AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'employee_id' => $employee->id,
                    'assigned_by' => $user->id,
                    'assigned_date' => now(),
                    'status' => 'active',
                    'assignment_notes' => 'Assigned due to custody change',
                    'custody_change_id' => $custodyChange->id,
                ]);

                // Update asset status
                $asset->update([
                    'assigned_to' => $employee->id,
                    'assigned_date' => now(),
                    'status' => 'assigned',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Custody updated successfully.',
                'custody_change' => $custodyChange->load(['updatedBy']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update custody: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a printable custody document.
     */
    public function generateDocument(CustodyChange $custodyChange): Response
    {
        $user = Auth::user();
        $custodyChange->loadMissing('employee');
        $this->abortUnlessCanUpdateEmployeeCustody($user, $custodyChange->employee);

        $custodyChange->load(['employee.company', 'employee.department', 'updatedBy']);

        // Validate custody change using service
        $documentService = new CustodyDocumentService();
        $errors = $documentService->validateCustodyChange($custodyChange);
        
        if (!empty($errors)) {
            abort(422, 'Invalid custody change: ' . implode(', ', $errors));
        }

        // Set locale to Arabic for the document
        app()->setLocale('ar');

        // Load actual Asset models with relationships for the document
        $previousAssetIds = collect($custodyChange->previous_state['assets'] ?? [])->pluck('id')->filter();
        $newAssetIds = collect($custodyChange->new_state['assets'] ?? [])->pluck('id')->filter();
        
        $previousAssets = Asset::with(['assetTemplate', 'category', 'location', 'company'])
            ->whereIn('id', $previousAssetIds)
            ->get();
            
        $newAssets = Asset::with(['assetTemplate', 'category', 'location', 'company'])
            ->whereIn('id', $newAssetIds)
            ->get();

        return Inertia::render('Documents/CustodyChangeDocument', [
            'custodyChange' => $custodyChange,
            'employee' => $custodyChange->employee,
            'previousAssets' => $previousAssets,
            'newAssets' => $newAssets,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'locale' => 'ar', // Pass Arabic locale to frontend
        ]);
    }

    /**
     * Upload a signed document for a custody change.
     */
    public function uploadDocument(Request $request, CustodyChange $custodyChange): JsonResponse
    {
        $user = Auth::user();
        $custodyChange->loadMissing('employee');
        if (! $this->canUpdateEmployeeCustody($user) || ! $this->canAccessEmployee($user, $custodyChange->employee)) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,gif|max:10240', // 10MB max
            'type' => 'required|in:signed,proof',
        ]);

        try {
            // Delete old document if exists
            if ($custodyChange->document_path && Storage::disk('public')->exists($custodyChange->document_path)) {
                Storage::disk('public')->delete($custodyChange->document_path);
            }

            // Store new document
            $documentPath = $request->file('document')->store('custody-documents', 'public');

            // Update custody change
            $custodyChange->update([
                'document_path' => $documentPath,
                'status' => $validated['type'] === 'signed' ? 'signed' : $custodyChange->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'document_path' => $documentPath,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available assets for assignment (API endpoint).
     */
    public function getAvailableAssets(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->canUpdateEmployeeCustody($user), 403);

        $ownedCompanyIds = $this->employeeQueryableCompanyIds($user);

        $query = Asset::whereIn('company_id', $ownedCompanyIds->isEmpty() ? [-1] : $ownedCompanyIds)
            ->where('status', 'available')
            ->with(['assetCategory', 'location', 'company', 'assetTemplate']);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('model_name', 'like', "%{$search}%")
                  ->orWhere('model_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $assets = $query->limit(50)->get();

        return response()->json($assets);
    }
}
