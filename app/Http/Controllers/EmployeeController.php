<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Employee;
use App\Models\Nationality;
use App\Models\Shift;
use App\Models\Team;
use App\Services\EmployeeExpiryService;
use App\Services\EmployeePortalUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EmployeeController extends Controller
{
    /**
     * Resolve companies the current user can search employees in.
     *
     * @return array<int>|null null means all companies (super-admin)
     */
    private function searchableCompanyIdsForUser($user): ?array
    {
        if ($user->hasRole('super-admin')) {
            return null;
        }

        return $user->ownedCompanies()
            ->pluck('id')
            ->merge(
                $user->accessibleCompanies()->pluck('companies.id')
            )
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Normalize Arabic alef variants to improve search matching.
     */
    private function normalizeSearchTerm(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
    }

    /**
     * Apply robust search across employee fields with Arabic normalization.
     */
    private function applyEmployeeSearch($query, string $rawSearch): void
    {
        $normalizedSearch = $this->normalizeSearchTerm($rawSearch);
        if ($normalizedSearch === '') {
            return;
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $normalizedSearch) ?: [],
            static fn (string $token): bool => $token !== ''
        ));

        $normalizeSql = static fn (string $column): string => "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا')";
        $fullNameSql = "CONCAT_WS(' ', employees.first_name, employees.father_name, employees.last_name)";
        $normalizedFullNameSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$fullNameSql}, ''), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا')";

        $query->where(function ($outerQuery) use ($tokens, $rawSearch, $normalizeSql, $normalizedFullNameSql) {
            foreach ($tokens as $token) {
                $outerQuery->where(function ($tokenQuery) use ($token, $normalizeSql, $normalizedFullNameSql) {
                    $like = '%'.$token.'%';

                    $tokenQuery
                        ->orWhereRaw("{$normalizeSql('employees.first_name')} LIKE ?", [$like])
                        ->orWhereRaw("{$normalizeSql('employees.father_name')} LIKE ?", [$like])
                        ->orWhereRaw("{$normalizeSql('employees.last_name')} LIKE ?", [$like])
                        ->orWhereRaw("{$normalizedFullNameSql} LIKE ?", [$like])
                        ->orWhereRaw("{$normalizeSql('employees.job_title')} LIKE ?", [$like])
                        ->orWhereRaw("{$normalizeSql('employees.department')} LIKE ?", [$like])
                        ->orWhere('employees.employee_id', 'like', $like)
                        ->orWhere('employees.work_email', 'like', $like)
                        ->orWhere('employees.personal_email', 'like', $like)
                        ->orWhere('employees.work_phone', 'like', $like)
                        ->orWhere('employees.personal_phone', 'like', $like);
                });
            }

            // Fallback using raw input for non-Arabic patterns.
            $outerQuery
                ->orWhere('employees.first_name', 'like', "%{$rawSearch}%")
                ->orWhere('employees.father_name', 'like', "%{$rawSearch}%")
                ->orWhere('employees.last_name', 'like', "%{$rawSearch}%")
                ->orWhereRaw("CONCAT_WS(' ', employees.first_name, employees.father_name, employees.last_name) LIKE ?", ["%{$rawSearch}%"])
                ->orWhere('employees.employee_id', 'like', "%{$rawSearch}%")
                ->orWhere('employees.work_email', 'like', "%{$rawSearch}%")
                ->orWhere('employees.personal_email', 'like', "%{$rawSearch}%")
                ->orWhere('employees.job_title', 'like', "%{$rawSearch}%")
                ->orWhere('employees.department', 'like', "%{$rawSearch}%");
        });
    }

    /**
     * Display a listing of the employees.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->pluck('id');

        // If user doesn't have a company, redirect to create one
        if (! $companies) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        $query = Employee::whereIn('company_id', $companies)
            ->with(['assets', 'company'])
            ->withCount(['assets', 'reportedTickets']);

        // Apply company filter
        if ($request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');
            // Verify user owns this company
            if ($companies->contains($companyId)) {
                $query->where('company_id', $companyId);
            }
        }

        // Apply search filter
        if ($request->filled('search')) {
            $this->applyEmployeeSearch($query, (string) $request->input('search'));
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        // Apply department filter
        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        // Get all filtered employees for statistics (before pagination)
        $filteredQuery = clone $query;
        $allFilteredEmployees = $filteredQuery->get();

        // Calculate statistics from all filtered employees
        $total = $allFilteredEmployees->count();
        $active = $allFilteredEmployees->where('employment_status', 'active')->count();
        $inactive = $allFilteredEmployees->where('employment_status', 'inactive')->count();
        $terminated = $allFilteredEmployees->where('employment_status', 'terminated')->count();
        $totalTickets = $allFilteredEmployees->sum('reported_tickets_count');

        // Calculate employees needing attention (documents expiring within 30 days)
        $needingAttention = $allFilteredEmployees->filter(function ($emp) {
            $today = now();
            $thirtyDaysFromNow = $today->copy()->addDays(30);

            $expiryDates = collect([
                $emp->residence_expiry_date,
                $emp->contract_end_date,
                $emp->exit_reentry_visa_expiry,
                $emp->passport_expiry_date,
                $emp->insurance_expiry_date,
            ])->filter();

            return $expiryDates->contains(function ($date) use ($today, $thirtyDaysFromNow) {
                if (! $date) {
                    return false;
                }
                $expiryDate = \Carbon\Carbon::parse($date);

                return $expiryDate->between($today, $thirtyDaysFromNow);
            });
        })->count();

        $activePercentage = $total > 0 ? round(($active / $total) * 100) : 0;

        // Now paginate for display
        $employees = $query->latest()->paginate(12)->withQueryString();

        // Get companies list for filter dropdown
        $companiesList = $user->ownedCompanies()->orderBy('name_en')->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'companies' => $companiesList,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'terminated' => $terminated,
                'totalTickets' => $totalTickets,
                'needingAttention' => $needingAttention,
                'activePercentage' => $activePercentage,
            ],
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'department' => $request->input('department'),
                'company_id' => $request->input('company_id'),
            ],
        ]);
    }

    /**
     * Display all employees with documents expiring within the configured threshold.
     */
    public function expiringDocuments(Request $request, EmployeeExpiryService $employeeExpiryService): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->pluck('id');

        if (! $companies) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        $days = (int) ($request->input('days') ?: EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD);

        $all = $employeeExpiryService->getExpiringDocumentRows($companies, $days);

        // Simple manual pagination for the computed collection
        $page = (int) $request->input('page', 1);
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $items = $all->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $all->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Employees/ExpiringDocuments', [
            'expiringEmployees' => $paginator,
            'days' => $days,
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->get();
        $currentCompany = $user->currentCompany();

        // If user doesn't have any companies, redirect to create one
        if ($companies->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Get Saudi Arabia as default residence country
        $saudiArabia = Country::where('code', 'SA')->first();

        return Inertia::render('Employees/Create', [
            'companies' => $companies,
            'currentCompany' => $currentCompany,
            'defaultCompanyId' => $currentCompany?->id,
            'countries' => Country::active()->orderByName()->get(),
            'nationalities' => Nationality::active()->orderByName()->get(),
            'defaultResidenceCountryId' => $saudiArabia?->id,
            'shifts' => Shift::orderBy('name')->get(),
            'canManagePortalAccount' => $user->hasRole('super-admin'),
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // Debug logging
        \Log::info('Employee creation debug', [
            'user_id' => $user->id,
            'owned_company_ids' => $ownedCompanyIds->toArray(),
            'request_company_id' => $request->input('company_id'),
            'request_data' => $request->all(),
        ]);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        \Log::info('Starting validation...');

        // Convert empty strings to null for unique fields so validation allows multiple nulls
        $request->merge([
            'employee_id' => trim((string) $request->input('employee_id', '')) === '' ? null : $request->input('employee_id'),
            'personal_email' => trim((string) $request->input('personal_email', '')) === '' ? null : $request->input('personal_email'),
            'work_email' => trim((string) $request->input('work_email', '')) === '' ? null : $request->input('work_email'),
            'personal_phone' => trim((string) $request->input('personal_phone', '')) === '' ? null : $request->input('personal_phone'),
            'work_phone' => trim((string) $request->input('work_phone', '')) === '' ? null : $request->input('work_phone'),
        ]);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::in($ownedCompanyIds)],
            'employee_id' => 'nullable|string|max:50|unique:employees,employee_id',
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality_id' => 'required|exists:nationalities,id',
            'residence_country_id' => 'required|exists:countries,id',
            'birth_date' => 'nullable|date',
            'personal_email' => 'nullable|email|max:255|unique:employees,personal_email',
            'work_email' => 'nullable|email|max:255|unique:employees,work_email',
            'personal_phone' => 'nullable|string|max:20',
            'work_phone' => 'nullable|string|max:20',
            'fingerprint_device_id' => 'nullable|string|max:50',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_address' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'allowance_housing' => 'nullable|numeric|min:0',
            'allowance_transportation' => 'nullable|numeric|min:0',
            'allowance_other' => 'nullable|numeric|min:0',
            'allowance_food' => 'nullable|numeric|min:0',
            'allowance_personal_car' => 'nullable|numeric|min:0',
            'social_insurance_deduction_rate' => 'nullable|numeric|min:0|max:100',
            'manager' => 'nullable|string|max:255',
            'direct_manager' => 'nullable|string|max:255',
            'additional_approver_2' => 'nullable|string|max:255',
            'additional_approver_3' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'employment_status' => 'nullable|in:active,inactive,terminated',
            'termination_date' => 'nullable|date',
            'departure_date' => 'nullable|date',
            'departure_reason' => 'nullable|string|max:500',
            'id_number' => 'nullable|string|max:50',
            'residence_expiry_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'exit_reentry_visa_expiry' => 'nullable|date',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry_date' => 'nullable|date',
            'insurance_policy' => 'nullable|string|max:255',
            'insurance_expiry_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_email' => 'nullable|email|max:255',
            'emergency_contact_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'annual_leave_balance' => 'nullable|integer|min:0',
            'portal_password' => 'nullable|string|min:8|confirmed',
            'portal_password_reset' => 'nullable|boolean',
        ], [
            'employee_id.unique' => __('employees.employee_id_already_used'),
            'personal_email.unique' => __('employees.email_already_used'),
            'work_email.unique' => __('employees.email_already_used'),
        ]);

        if (! $isSuperAdmin && (
            $request->filled('portal_password') ||
            $request->filled('portal_password_confirmation') ||
            $request->boolean('portal_password_reset')
        )) {
            abort(403, 'Access denied. Super admin role required.');
        }

        \Log::info('Validation passed, checking department...');

        // Validate department belongs to selected company if specified
        if (! empty($validated['department_id'])) {
            $department = \App\Models\Department::where('id', $validated['department_id'])
                ->where('company_id', $validated['company_id'])
                ->first();

            if (! $department) {
                return back()->withErrors(['department_id' => 'Invalid department selection for the chosen company.']);
            }
        }

        \Log::info('Department check passed, setting defaults...');

        // Set default employment status if not provided
        if (empty($validated['employment_status'])) {
            $validated['employment_status'] = 'active';
        }

        if (isset($validated['employee_id']) && trim((string) $validated['employee_id']) === '') {
            $validated['employee_id'] = null;
        }

        // Ensure non-nullable salary columns have default 0 (DB does not allow null)
        $validated['basic_salary'] = $validated['basic_salary'] ?? 0;
        $validated['allowances'] = $validated['allowances'] ?? 0;
        $validated['allowance_housing'] = $validated['allowance_housing'] ?? null;
        $validated['allowance_transportation'] = $validated['allowance_transportation'] ?? null;
        $validated['allowance_other'] = $validated['allowance_other'] ?? null;
        $validated['allowance_food'] = $validated['allowance_food'] ?? null;
        $validated['allowance_personal_car'] = $validated['allowance_personal_car'] ?? null;
        $validated['social_insurance_deduction_rate'] = $validated['social_insurance_deduction_rate'] ?? null;

        try {
            \Log::info('Creating employee...');
            $employee = Employee::create($validated);
            \Log::info('Employee created successfully', ['employee_id' => $employee->id]);

            app(EmployeePortalUserService::class)->createOrSyncPortalUser(
                $employee,
                $isSuperAdmin ? ($validated['portal_password'] ?? null) : null,
                false
            );

            return redirect()->route('employees.show', $employee)
                ->with('success', 'Employee created successfully.');
        } catch (\Exception $e) {
            \Log::error('Employee creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Employee creation failed: '.$e->getMessage()]);
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // If user doesn't have a company, redirect to create one
        if (! $ownedCompanyIds) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee
        if (! $ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $employee->load([
            'company',
            'nationality',
            'residenceCountry',
            'user.team',
            'shift',
            'assets.assetCategory',
            'assetAssignments.asset.assetCategory',
            'reportedTickets.ticketCategory',
            'debts',
            'leaves' => fn ($q) => $q->orderBy('start_date', 'desc'),
        ]);
        $employee->loadCount(['assets', 'reportedTickets']);
        $employee->append('remaining_annual_leave_balance');

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'portalAccount' => [
                'exists' => (bool) $employee->user_id,
                'email' => $employee->work_email ?: $employee->user?->email,
            ],
            'assignedTeamName' => $employee->user?->team?->name,
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->get();
        $ownedCompanyIds = $companies->pluck('id');

        // If user doesn't have any companies, redirect to create one
        if ($companies->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee (owns the employee's company)
        if (! $ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        // Get Saudi Arabia as default residence country
        $saudiArabia = Country::where('code', 'SA')->first();

        $employee->load(['debts', 'user']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'companies' => $companies,
            'defaultCompanyId' => $employee->company_id,
            'countries' => Country::active()->orderByName()->get(),
            'nationalities' => Nationality::active()->orderByName()->get(),
            'defaultResidenceCountryId' => $saudiArabia?->id,
            'departments' => \App\Models\Department::all(),
            'locations' => \App\Models\Location::all(),
            'shifts' => Shift::orderBy('name')->get(),
            'canManagePortalAccount' => $user->hasRole('super-admin'),
            'portalAccount' => [
                'exists' => (bool) $employee->user_id,
                'email' => $employee->work_email ?: $employee->user?->email,
            ],
            'availableTeams' => Team::query()
                ->where('owner_id', $user->id)
                ->orWhere('id', $user->team_id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->unique('id')
                ->values(),
            'assignedTeamId' => $employee->user?->team_id,
            'roleCompanies' => \App\Models\Company::query()
                ->orderBy('name_en')
                ->orderBy('name_ar')
                ->get(['id', 'name_en', 'name_ar'])
                ->map(fn (\App\Models\Company $company) => [
                    'id' => $company->id,
                    'name' => trim(($company->name_en ?? '').' '.($company->name_ar ?? '')) ?: (string) $company->id,
                ])
                ->values(),
            'assignedRoleCompanyIds' => $employee->user?->accessibleCompanies()->pluck('companies.id')->values() ?? [],
        ]);
    }

    /**
     * Update the fingerprint device link for an employee (link to fingerprint device API employee).
     */
    public function updateFingerprintLink(Request $request, Employee $employee): JsonResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        if (! $ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'fingerprint_device_id' => 'nullable|string|max:100',
        ]);

        $employee->update(['fingerprint_device_id' => $validated['fingerprint_device_id'] ?? null]);

        return response()->json([
            'success' => true,
            'fingerprint_device_id' => $employee->fingerprint_device_id,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee (owns the employee's current company)
        if (! $ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        // Convert empty strings to null for unique fields
        $request->merge([
            'employee_id' => trim((string) $request->input('employee_id', '')) === '' ? null : $request->input('employee_id'),
            'personal_email' => trim((string) $request->input('personal_email', '')) === '' ? null : $request->input('personal_email'),
            'work_email' => trim((string) $request->input('work_email', '')) === '' ? null : $request->input('work_email'),
            'personal_phone' => trim((string) $request->input('personal_phone', '')) === '' ? null : $request->input('personal_phone'),
            'work_phone' => trim((string) $request->input('work_phone', '')) === '' ? null : $request->input('work_phone'),
        ]);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::in($ownedCompanyIds)],
            'employee_id' => 'nullable|string|max:50|unique:employees,employee_id,'.$employee->id,
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality_id' => 'required|exists:nationalities,id',
            'residence_country_id' => 'required|exists:countries,id',
            'birth_date' => 'nullable|date',
            'personal_email' => 'nullable|email|max:255|unique:employees,personal_email,'.$employee->id,
            'work_email' => 'nullable|email|max:255|unique:employees,work_email,'.$employee->id,
            'personal_phone' => 'nullable|string|max:20',
            'work_phone' => 'nullable|string|max:20',
            'fingerprint_device_id' => 'nullable|string|max:50',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_address' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'allowance_housing' => 'nullable|numeric|min:0',
            'allowance_transportation' => 'nullable|numeric|min:0',
            'allowance_other' => 'nullable|numeric|min:0',
            'allowance_food' => 'nullable|numeric|min:0',
            'allowance_personal_car' => 'nullable|numeric|min:0',
            'social_insurance_deduction_rate' => 'nullable|numeric|min:0|max:100',
            'manager' => 'nullable|string|max:255',
            'direct_manager' => 'nullable|string|max:255',
            'additional_approver_2' => 'nullable|string|max:255',
            'additional_approver_3' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'termination_date' => 'nullable|date',
            'departure_date' => 'nullable|date',
            'departure_reason' => 'nullable|string|max:500',
            'employment_status' => 'required|in:active,inactive,terminated',
            'id_number' => 'nullable|string|max:50',
            'residence_expiry_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'exit_reentry_visa_expiry' => 'nullable|date',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry_date' => 'nullable|date',
            'insurance_policy' => 'nullable|string|max:255',
            'insurance_expiry_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_email' => 'nullable|email|max:255',
            'emergency_contact_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'annual_leave_balance' => 'nullable|integer|min:0',
            'portal_password' => 'nullable|string|min:8|confirmed',
            'portal_password_reset' => 'nullable|boolean',
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'role_company_ids' => ['array'],
            'role_company_ids.*' => ['integer', Rule::exists('companies', 'id')],
        ], [
            'employee_id.unique' => __('employees.employee_id_already_used'),
            'personal_email.unique' => __('employees.email_already_used'),
            'work_email.unique' => __('employees.email_already_used'),
        ]);

        if (! $isSuperAdmin && (
            $request->filled('portal_password') ||
            $request->filled('portal_password_confirmation') ||
            $request->boolean('portal_password_reset')
        )) {
            abort(403, 'Access denied. Super admin role required.');
        }

        // Validate department belongs to selected company if specified
        if (! empty($validated['department_id'])) {
            $department = \App\Models\Department::where('id', $validated['department_id'])
                ->where('company_id', $validated['company_id'])
                ->first();

            if (! $department) {
                return back()->withErrors(['department_id' => 'Invalid department selection for the chosen company.']);
            }
        }

        if (isset($validated['employee_id']) && trim((string) $validated['employee_id']) === '') {
            $validated['employee_id'] = null;
        }

        // Ensure non-nullable salary columns have default 0 (DB does not allow null)
        $validated['basic_salary'] = $validated['basic_salary'] ?? 0;
        $validated['allowances'] = $validated['allowances'] ?? 0;

        $employee->update($validated);

        app(EmployeePortalUserService::class)->createOrSyncPortalUser(
            $employee->fresh(),
            $isSuperAdmin ? ($validated['portal_password'] ?? null) : null,
            $isSuperAdmin && (bool) ($validated['portal_password_reset'] ?? false)
        );

        $employee->refresh()->load('user');

        if ($employee->user) {
            $selectedTeamId = isset($validated['team_id']) ? (int) $validated['team_id'] : null;
            $selectedTeam = $selectedTeamId ? Team::query()->where('id', $selectedTeamId)->first() : null;

            if ($selectedTeamId && ! $selectedTeam) {
                return back()->withErrors(['team_id' => 'Selected team does not exist.']);
            }

            if ($selectedTeam && ! ($selectedTeam->owner_id === $user->id || $selectedTeam->id === $user->team_id)) {
                return back()->withErrors(['team_id' => 'You are not allowed to assign this team.']);
            }

            $employee->user->update([
                'team_id' => $selectedTeam?->id,
                'joined_team_at' => $selectedTeam ? now() : null,
            ]);

            $employee->user->roles()
                ->where('name', 'team-member')
                ->detach();

            if ($selectedTeam) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($selectedTeam->id);

                $teamMemberRole = Role::query()->firstOrCreate([
                    'name' => 'team-member',
                    'guard_name' => 'web',
                    'team_id' => $selectedTeam->id,
                ]);

                $employee->user->assignRole($teamMemberRole);
            }

            $employee->user->accessibleCompanies()->sync($validated['role_company_ids'] ?? []);
        }

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        $queryParams = $request->only(['search', 'status', 'department', 'company_id']);

        \Log::info('Employee destroy attempted', [
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'employee_company_id' => $employee->company_id,
        ]);

        // Allow delete only if the employee's company is one of the user's owned companies
        if (! $user->ownedCompanies()->where('id', $employee->company_id)->exists()) {
            \Log::warning('Employee destroy forbidden: user does not own company', [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
            ]);
            abort(403);
        }

        // Unassign all assets from this employee (assets remain in system, assigned_to = null)
        $assetsCount = $employee->assets()->count();
        if ($assetsCount > 0) {
            $employee->assets()->update(['assigned_to' => null]);
            \Log::info('Employee destroy: unassigned assets', ['employee_id' => $employee->id, 'assets_count' => $assetsCount]);
        }

        // Check if employee has open tickets
        $openTicketsCount = $employee->reportedTickets()->whereNotIn('status', ['resolved', 'closed'])->count();
        if ($openTicketsCount > 0) {
            \Log::info('Employee destroy blocked: has open tickets', ['employee_id' => $employee->id, 'open_tickets' => $openTicketsCount]);

            return redirect()->route('employees.index', $queryParams)
                ->with('error', __('employees.cannot_delete_with_tickets'));
        }

        try {
            $employee->delete();
            \Log::info('Employee deleted successfully', ['employee_id' => $employee->id]);
        } catch (\Throwable $e) {
            \Log::error('Employee delete failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('employees.index', $queryParams)
                ->with('error', __('employees.delete_failed'));
        }

        return redirect()->route('employees.index', $queryParams)
            ->with('success', __('employees.deleted_successfully'));
    }

    /**
     * Search employees for async selection.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = $request->get('q', '');
        $companyId = $request->get('company_id');
        $departmentId = $request->get('department_id');

        // Get all companies the user owns
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // If user doesn't have any companies, return empty results
        if ($ownedCompanyIds->isEmpty()) {
            return response()->json([]);
        }

        $employees = Employee::query()
            ->when($companyId, function ($q) use ($companyId, $ownedCompanyIds) {
                // If a specific company is requested, use it (but only if user owns it)
                if ($ownedCompanyIds->contains($companyId)) {
                    return $q->where('company_id', $companyId);
                }

                // Otherwise, search across all owned companies
                return $q->whereIn('company_id', $ownedCompanyIds);
            }, function ($q) use ($ownedCompanyIds) {
                // If no specific company requested, search across all owned companies
                return $q->whereIn('company_id', $ownedCompanyIds);
            })
            ->when($departmentId, function ($q) use ($departmentId) {
                return $q->where('department', $departmentId);
            })
            ->when($query, function ($q) use ($query) {
                $this->applyEmployeeSearch($q, (string) $query);
            })
            ->where('employment_status', 'active') // Only active employees
            ->with('company')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->employee_id,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'basic_salary' => $employee->basic_salary,
                    'allowances' => $employee->allowances,
                    'email' => $employee->work_email ?: $employee->personal_email,
                    'job_title' => $employee->job_title,
                    'department' => $employee->department,
                    'company_name' => $employee->company->name_en,
                    'display_name' => ($employee->employee_id ? "{$employee->employee_id}: " : '').
                        "{$employee->first_name} {$employee->last_name}".
                        ($employee->job_title ? " - {$employee->job_title}" : '').
                        " ({$employee->company->name_en})",
                ];
            });

        return response()->json($employees);
    }

    /**
     * Global employee search used by the fixed top search bar.
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = trim((string) $request->get('q', ''));
        $companyIds = $this->searchableCompanyIdsForUser($user);

        if ($query === '') {
            return response()->json([]);
        }

        if (is_array($companyIds) && empty($companyIds)) {
            return response()->json([]);
        }

        $employees = Employee::query()
            ->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->with('company')
            ->when($query, function ($q) use ($query) {
                $this->applyEmployeeSearch($q, $query);
            })
            ->orderBy('first_name')
            ->orderBy('father_name')
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(function (Employee $employee): array {
                $fullName = trim(implode(' ', array_filter([
                    $employee->first_name,
                    $employee->father_name,
                    $employee->last_name,
                ])));

                return [
                    'id' => $employee->id,
                    'full_name' => $fullName !== '' ? $fullName : (string) $employee->id,
                    'employee_id' => $employee->employee_id,
                    'company_name' => $employee->company?->name_en ?: $employee->company?->name_ar,
                    'employment_status' => $employee->employment_status,
                ];
            })
            ->values();

        return response()->json($employees);
    }
}
