<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\Company;
use App\Models\Country;
use App\Models\Nationality;
use App\Models\Department;
use App\Services\EmployeeImportService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeImportController extends Controller
{
    use AuthorizesEmployeeAccess;

    protected $importService;

    public function __construct(EmployeeImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Show the import instructions page.
     */
    public function instructions(): Response|RedirectResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $companyIds = $this->employeeQueryableCompanyIds($user);
        $companies = Company::query()->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)->orderBy('name_en')->get();

        if ($companies->isEmpty()) {
            return redirect()->route('employees.index')
                ->with('info', 'No companies available to import employees.');
        }

        return Inertia::render('Employees/Import/Instructions', [
            'companies' => $companies,
            'currentCompany' => $user->currentCompany(), // Keep for backwards compatibility
            'requiredFields' => $this->getRequiredFields(),
            'optionalFields' => $this->getOptionalFields(),
        ]);
    }

    /**
     * Show the upload page.
     */
    public function upload(): Response|RedirectResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $companyIds = $this->employeeQueryableCompanyIds($user);
        $companies = Company::query()->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)->orderBy('name_en')->get();

        if ($companies->isEmpty()) {
            return redirect()->route('employees.index')
                ->with('info', 'No companies available to import employees.');
        }

        return Inertia::render('Employees/Import/Upload', [
            'companies' => $companies,
            'currentCompany' => $user->currentCompany(), // Keep for backwards compatibility
        ]);
    }

    /**
     * Download sample CSV file.
     */
    public function sampleCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $companyIds = $this->employeeQueryableCompanyIds($user);
        $companies = Company::query()->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)->get();

        if ($companies->isEmpty()) {
            abort(403, 'No companies associated with user.');
        }

        // Get company ID from request, or use first company as default
        $companyId = $request->query('company_id');
        $company = $companyId ? $companies->firstWhere('id', (int) $companyId) : $companies->first();

        if (!$company) {
            abort(403, 'Invalid company selection.');
        }

        $csv = $this->importService->generateSampleCsv($company);
        $filename = 'employees-sample-' . $company->slug . '-' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export existing employees to Excel (active records only; soft-deleted excluded).
     */
    public function exportCsv(Request $request): BinaryFileResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $companyIds = $this->employeeQueryableCompanyIds($user);
        $companies = Company::query()->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)->get();

        if ($companies->isEmpty()) {
            abort(403, 'No companies associated with user.');
        }

        // Get company ID from request, or use first company as default
        $companyId = $request->query('company_id');
        $company = $companyId ? $companies->firstWhere('id', (int) $companyId) : $companies->first();

        if (!$company) {
            abort(403, 'Invalid company selection.');
        }

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->withoutTrashed()
            ->with(['nationality', 'residenceCountry', 'department'])
            ->orderBy('employee_id')
            ->get();

        $filename = 'employees-export-' . $company->slug . '-' . date('Y-m-d') . '.xlsx';

        return Excel::download(
            new EmployeesExport($employees, $this->importService),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Process uploaded CSV file for validation.
     */
    public function processUpload(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $ownedCompanyIds = $this->employeeQueryableCompanyIds($user);

        if ($ownedCompanyIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No companies associated with user.',
            ], 403);
        }

        $maxImportKb = (int) config('imports.employee_file_max_kb', 51200);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:' . $maxImportKb,
            'company_id' => 'required|integer|in:' . $ownedCompanyIds->implode(','),
            'import_mode' => 'sometimes|string|in:create,update',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file or company selection.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $company = Company::findOrFail($request->input('company_id'));
            
            // Double-check user owns this company
            if (!$ownedCompanyIds->contains($company->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company selection.',
                ], 403);
            }

            $file = $request->file('file');
            $filename = 'import_' . uniqid() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('imports', $filename, 'local');

            $importMode = $request->input('import_mode') === 'update'
                ? EmployeeImportService::IMPORT_MODE_UPDATE
                : EmployeeImportService::IMPORT_MODE_CREATE;

            // Process and validate the CSV
            $result = $this->importService->validateCsv($path, $company, $importMode);

            if ($result['success']) {
                // Store the validated data temporarily
                $importId = uniqid();
                $validatedDataPath = 'imports/validated_' . $importId . '.json';
                Storage::disk('local')->put($validatedDataPath, json_encode([
                    'data' => $result['data'],
                    'company_id' => $company->id,
                ]));

                return response()->json([
                    'success' => true,
                    'import_id' => $importId,
                    'summary' => $result['summary'],
                    'data' => $result['data'],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'CSV validation failed.',
                    'errors' => $result['errors'],
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('CSV upload processing failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'company_id' => $request->input('company_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process CSV file. Please check the format and try again.',
            ], 500);
        }
    }

    /**
     * Execute the import process.
     */
    public function executeImport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $ownedCompanyIds = $this->employeeQueryableCompanyIds($user);

        if ($ownedCompanyIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No companies associated with user.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'import_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid import ID.',
            ], 422);
        }

        try {
            $importId = $request->input('import_id');
            $validatedDataPath = 'imports/validated_' . $importId . '.json';

            if (!Storage::disk('local')->exists($validatedDataPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import data not found or expired. Please re-upload your CSV file.',
                ], 404);
            }

            $validatedDataWithCompany = json_decode(Storage::disk('local')->get($validatedDataPath), true);
            $validatedData = $validatedDataWithCompany['data'];
            $companyId = $validatedDataWithCompany['company_id'];

            // Verify user still owns this company
            if (!$ownedCompanyIds->contains($companyId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company access.',
                ], 403);
            }

            $company = Company::findOrFail($companyId);

            // Execute the import
            $result = $this->importService->importEmployees($validatedData, $company, $user, $importId);

            // Clean up temporary files
            Storage::disk('local')->delete($validatedDataPath);

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully.',
                'summary' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('CSV import execution failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'import_id' => $request->input('import_id'),
                'exception_class' => $e::class,
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'hint' => 'If this follows "Employee CSV import failed on row", use spreadsheet_row_estimate and payload_preview there.',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import execution failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Get required fields for CSV import.
     */
    protected function getRequiredFields(): array
    {
        return [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
        ];
    }

    /**
     * Get optional fields for CSV import.
     */
    protected function getOptionalFields(): array
    {
        return [
            'id' => 'Employee ID (for updates)',
            'employee_id' => 'Employee ID Number',
            'father_name' => 'Father Name',
            'nationality' => 'Nationality',
            'residence_country' => 'Residence Country',
            'birth_date' => 'Birth Date (YYYY-MM-DD)',
            'personal_email' => 'Personal Email',
            'work_email' => 'Work Email',
            'personal_phone' => 'Personal Mobile',
            'work_phone' => 'Work Mobile',
            'fingerprint_device_id' => 'Fingerprint Device ID',
            'work_address' => 'Work Address',
            'department' => 'Department',
            'job_title' => 'Job Title',
            'shift_id' => 'Shift ID',
            'basic_salary' => 'Basic Salary',
            'allowances' => 'Allowances',
            'allowance_housing' => 'Housing Allowance',
            'allowance_transportation' => 'Transportation Allowance',
            'allowance_other' => 'Other Allowances',
            'allowance_food' => 'Food Allowance',
            'allowance_personal_car' => 'Personal Car Allowance',
            'social_insurance_deduction_rate' => 'Social Insurance Deduction Rate (%)',
            'manager' => 'Manager',
            'direct_manager' => 'Direct Manager',
            'additional_approver_2' => 'Additional Approver 2',
            'additional_approver_3' => 'Additional Approver 3',
            'hire_date' => 'Hire Date (YYYY-MM-DD)',
            'probation_end_date' => 'Probation End Date (YYYY-MM-DD)',
            'employment_status' => 'Employment Status (active/inactive/terminated)',
            'termination_date' => 'Termination Date (YYYY-MM-DD)',
            'departure_date' => 'Departure Date (YYYY-MM-DD)',
            'departure_reason' => 'Departure Reason',
            'id_number' => 'ID Number',
            'residence_expiry_date' => 'Residence Expiry Date (YYYY-MM-DD)',
            'contract_end_date' => 'Contract End Date (YYYY-MM-DD)',
            'exit_reentry_visa_expiry' => 'Exit/Re-entry Visa Expiry (YYYY-MM-DD)',
            'passport_number' => 'Passport Number',
            'passport_expiry_date' => 'Passport Expiry Date (YYYY-MM-DD)',
            'insurance_policy' => 'Insurance Policy',
            'insurance_expiry_date' => 'Insurance Expiry Date (YYYY-MM-DD)',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_contact_phone' => 'Emergency Contact Phone',
            'emergency_contact_email' => 'Emergency Contact Email',
            'emergency_contact_address' => 'Emergency Contact Address',
            'annual_leave_balance' => 'Annual Leave Entitlement (days per year, default 21)',
            'leave_accrued_balance' => 'Accrued Leave Balance (earned days to date; remaining = accrued minus approved leave deductions)',
            'notes' => 'Notes',
        ];
    }
} 