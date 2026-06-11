<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CustodyChange;
use App\Models\Employee;
use App\Models\Location;
use App\Services\CustodyAssignmentService;
use App\Services\CustodyDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CustodyController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private CustodyAssignmentService $custodyAssignmentService,
    ) {}

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

        $locations = Location::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'building', 'office_number']);

        $categories = AssetCategory::scoped(['company_id' => $employee->company_id])
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        $companies = Company::query()
            ->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar']);

        return Inertia::render('Employees/CustodyManagement', [
            'employee' => $employee->load(['company', 'nationality', 'residenceCountry']),
            'currentAssets' => $currentAssets,
            'availableAssets' => $availableAssets,
            'recentCustodyChanges' => $recentCustodyChanges,
            'locations' => $locations,
            'categories' => $categories,
            'companies' => $companies,
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

        $accessibleCompanyIds = $this->employeeQueryableCompanyIds($user)
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $validated = $request->validate([
            'new_asset_ids' => 'present|array',
            'new_asset_ids.*' => 'exists:assets,id',
            'changes_summary' => 'nullable|string|max:500',
        ]);

        try {
            $custodyChange = $this->custodyAssignmentService->updateEmployeeCustody(
                $employee,
                $user,
                $validated['new_asset_ids'],
                $validated['changes_summary'] ?? null,
                $accessibleCompanyIds
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.custody.custody_updated_successfully'),
                'custody_change' => $custodyChange,
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('messages.custody.failed_to_update_custody').': '.$e->getMessage(),
            ], 500);
        }
    }

    public function storeQuickAsset(Request $request, Employee $employee): JsonResponse
    {
        $user = Auth::user();
        if (! $this->canUpdateEmployeeCustody($user) || ! $this->canAccessEmployee($user, $employee)) {
            return response()->json(['error' => 'Unauthorized access to this employee.'], 403);
        }

        if (! $employee->company_id) {
            return response()->json(['error' => __('messages.custody.employee_missing_company')], 422);
        }

        $accessibleCompanyIds = $this->employeeQueryableCompanyIds($user)
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if (! in_array((int) $employee->company_id, $accessibleCompanyIds, true)) {
            return response()->json(['error' => 'Unauthorized access to this employee.'], 403);
        }

        $validated = $request->validate([
            'assets' => ['required', 'array', 'min:1', 'max:50'],
            'assets.*.asset_template_id' => ['required', 'exists:asset_templates,id'],
            'assets.*.location_id' => ['required', 'exists:locations,id'],
            'assets.*.serial_number' => ['nullable', 'string', 'max:255'],
            'assets.*.condition' => ['required', 'in:good,damaged'],
        ]);

        try {
            $createdAssets = DB::transaction(function () use ($employee, $user, $validated, $accessibleCompanyIds) {
                $assets = [];

                foreach ($validated['assets'] as $assetData) {
                    $assets[] = $this->custodyAssignmentService->createAvailableAssetForCompany(
                        (int) $employee->company_id,
                        $assetData
                    );
                }

                $currentAssetIds = $employee->assets()
                    ->where('status', 'assigned')
                    ->pluck('id')
                    ->map(fn ($id): string => (string) $id)
                    ->all();

                $createdAssetIds = array_map(fn (Asset $asset): string => (string) $asset->id, $assets);
                $newAssetIds = array_values(array_unique([...$currentAssetIds, ...$createdAssetIds]));

                $count = count($assets);
                $summary = $count === 1
                    ? __('messages.custody.quick_create_summary', ['tag' => $assets[0]->asset_tag])
                    : __('messages.custody.quick_create_multiple_summary', ['count' => $count]);

                $custodyChange = $this->custodyAssignmentService->updateEmployeeCustody(
                    $employee,
                    $user,
                    $newAssetIds,
                    $summary,
                    $accessibleCompanyIds
                );

                return [
                    'assets' => $assets,
                    'custody_change' => $custodyChange,
                ];
            });

            $count = count($createdAssets['assets']);
            $message = $count === 1
                ? __('messages.custody.quick_create_success', ['tag' => $createdAssets['assets'][0]->asset_tag])
                : __('messages.custody.quick_create_multiple_success', ['count' => $count]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'assets' => $createdAssets['assets'],
                'asset' => $createdAssets['assets'][0] ?? null,
                'custody_change' => $createdAssets['custody_change'],
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('messages.custody.quick_create_failed').': '.$e->getMessage(),
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
