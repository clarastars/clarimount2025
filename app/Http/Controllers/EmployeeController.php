<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Country;
use App\Models\Nationality;
use App\Models\Shift;
use App\Services\EmployeeExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->pluck('id');

        // If user doesn't have a company, redirect to create one
        if (!$companies) {
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
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('job_title', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
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
                $emp->insurance_expiry_date
            ])->filter();
            
            return $expiryDates->contains(function ($date) use ($today, $thirtyDaysFromNow) {
                if (!$date) return false;
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

        if (!$companies) {
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
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // Debug logging
        \Log::info('Employee creation debug', [
            'user_id' => $user->id,
            'owned_company_ids' => $ownedCompanyIds->toArray(),
            'request_company_id' => $request->input('company_id'),
            'request_data' => $request->all()
        ]);

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        \Log::info('Starting validation...');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::in($ownedCompanyIds)],
            'employee_id' => 'nullable|string|max:50|unique:employees,employee_id',
            'first_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality_id' => 'nullable|exists:nationalities,id',
            'residence_country_id' => 'nullable|exists:countries,id',
            'birth_date' => 'nullable|date',
            'email' => 'required|email|max:255|unique:employees,email',
            'personal_email' => 'nullable|email|max:255',
            'work_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'work_phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'fingerprint_device_id' => 'nullable|string|max:50',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_address' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'manager' => 'nullable|string|max:255',
            'direct_manager' => 'nullable|string|max:255',
            'additional_approver_2' => 'nullable|string|max:255',
            'additional_approver_3' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
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
        ]);

        \Log::info('Validation passed, checking department...');

        // Validate department belongs to selected company if specified
        if (!empty($validated['department_id'])) {
            $department = \App\Models\Department::where('id', $validated['department_id'])
                ->where('company_id', $validated['company_id'])
                ->first();

            if (!$department) {
                return back()->withErrors(['department_id' => 'Invalid department selection for the chosen company.']);
            }
        }

        \Log::info('Department check passed, setting defaults...');

        // Set default employment status if not provided
        if (empty($validated['employment_status'])) {
            $validated['employment_status'] = 'active';
        }

        try {
            \Log::info('Creating employee...');
            $employee = Employee::create($validated);
            \Log::info('Employee created successfully', ['employee_id' => $employee->id]);

            return redirect()->route('employees.show', $employee)
                ->with('success', 'Employee created successfully.');
        } catch (\Exception $e) {
            \Log::error('Employee creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Employee creation failed: ' . $e->getMessage()]);
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
        if (!$ownedCompanyIds) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee
        if (!$ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $employee->load([
            'company',
            'nationality',
            'residenceCountry',
            'shift',
            'assets.assetCategory',
            'assetAssignments.asset.assetCategory',
            'reportedTickets.ticketCategory'
        ]);
        $employee->loadCount(['assets', 'reportedTickets']);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
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
        if (!$ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        // Get Saudi Arabia as default residence country
        $saudiArabia = Country::where('code', 'SA')->first();

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
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        // If user doesn't have any companies, redirect to create one
        if ($ownedCompanyIds->isEmpty()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee (owns the employee's current company)
        if (!$ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::in($ownedCompanyIds)],
            'employee_id' => 'nullable|string|max:50|unique:employees,employee_id,' . $employee->id,
            'first_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality_id' => 'nullable|exists:nationalities,id',
            'residence_country_id' => 'nullable|exists:countries,id',
            'birth_date' => 'nullable|date',
            'email' => 'required|email|max:255|unique:employees,email,' . $employee->id,
            'personal_email' => 'nullable|email|max:255',
            'work_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'work_phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'fingerprint_device_id' => 'nullable|string|max:50',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_address' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'manager' => 'nullable|string|max:255',
            'direct_manager' => 'nullable|string|max:255',
            'additional_approver_2' => 'nullable|string|max:255',
            'additional_approver_3' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
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
        ]);

        // Validate department belongs to selected company if specified
        if (!empty($validated['department_id'])) {
            $department = \App\Models\Department::where('id', $validated['department_id'])
                ->where('company_id', $validated['company_id'])
                ->first();

            if (!$department) {
                return back()->withErrors(['department_id' => 'Invalid department selection for the chosen company.']);
            }
        }

        $employee->update($validated);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        $company = $user->currentCompany();

        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        // Check if user has access to this employee
        if ($employee->company_id !== $company->id) {
            abort(403);
        }

        // Check if employee has assets assigned
        if ($employee->assets()->count() > 0) {
            return redirect()->route('employees.index')
                ->with('error', 'Cannot delete employee who has assets assigned. Please return all assets first.');
        }

        // Check if employee has open tickets
        if ($employee->reportedTickets()->whereNotIn('status', ['resolved', 'closed'])->count() > 0) {
            return redirect()->route('employees.index')
                ->with('error', 'Cannot delete employee who has open tickets. Please resolve all tickets first.');
        }

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
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
                return $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('first_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('employee_id', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('job_title', 'like', "%{$query}%");
                });
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
                    'employee_id' => $employee->employee_id,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'email' => $employee->email,
                    'job_title' => $employee->job_title,
                    'department' => $employee->department,
                    'company_name' => $employee->company->name_en,
                    'display_name' => ($employee->employee_id ? "{$employee->employee_id}: " : '') .
                        "{$employee->first_name} {$employee->last_name}" .
                        ($employee->job_title ? " - {$employee->job_title}" : '') .
                        " ({$employee->company->name_en})",
                ];
            });

        return response()->json($employees);
    }
}
