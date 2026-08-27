<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\EntitlementSettlementApprovalService;
use App\Services\LeaveApprovalService;
use App\Services\SalaryCertificateApprovalService;
use App\Services\SalaryRunApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    private function userAccessibleCompanyIds(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return $user->ownedCompanies()
            ->pluck('id')
            ->merge(
                $user->accessibleCompanies()->pluck('companies.id')
            )
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function canViewCompanyReadOnly(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return app(\App\Services\EmployeeUserRoleService::class)
            ->canInAnyAssignedTeam($user, 'company.readonly');
    }

    private function canViewCompanyByTeamScope(Company $company): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return app(\App\Services\EmployeeUserRoleService::class)
            ->canForCompany($user, 'company.readonly', (int) $company->id);
    }

    /**
     * @return array{
     *     can_view_employees_readonly: bool,
     *     can_manage_employees: bool,
     *     can_view_company_leaves: bool,
     *     can_view_attendance_readonly: bool,
     *     can_manage_attendance_adjustments: bool,
     *     can_view_salary_runs_readonly: bool,
     *     can_approve_salary_runs: bool
     * }
     */
    private function companyCapabilityFlags(Company $company): array
    {
        $user = Auth::user();
        if (! $user) {
            return [
                'can_view_employees_readonly' => false,
                'can_manage_employees' => false,
                'can_view_company_leaves' => false,
                'can_view_attendance_readonly' => false,
                'can_manage_attendance_adjustments' => false,
                'can_view_salary_runs_readonly' => false,
                'can_approve_salary_runs' => false,
            ];
        }

        if ($user->hasRole('super-admin') || $this->canManageCompany($company)) {
            return [
                'can_view_employees_readonly' => true,
                'can_manage_employees' => true,
                'can_view_company_leaves' => true,
                'can_view_attendance_readonly' => true,
                'can_manage_attendance_adjustments' => true,
                'can_view_salary_runs_readonly' => true,
                'can_approve_salary_runs' => true,
            ];
        }

        $roleService = app(\App\Services\EmployeeUserRoleService::class);
        $companyId = (int) $company->id;

        return [
            'can_view_employees_readonly' => $roleService->canAnyForCompany(
                $user,
                ['employees.readonly', 'employees.manage'],
                $companyId
            ),
            'can_manage_employees' => $roleService->canForCompany($user, 'employees.manage', $companyId),
            'can_view_company_leaves' => $roleService->canForCompany($user, 'leaves.company.view', $companyId),
            'can_view_attendance_readonly' => $roleService->canForCompany($user, 'attendance.readonly', $companyId),
            'can_manage_attendance_adjustments' => $roleService->canForCompany($user, 'attendance.adjustments.manage', $companyId),
            'can_view_salary_runs_readonly' => $roleService->canAnyForCompany(
                $user,
                [
                    'salary-runs.readonly',
                    'salary-runs.approve',
                    'salary-runs.create',
                    'salary-runs.delete',
                    'salary-runs.debt-deductions.manage',
                ],
                $companyId
            ),
            'can_approve_salary_runs' => $roleService->canForCompany($user, 'salary-runs.approve', $companyId),
        ];
    }

    private function canManageCompany(Company $company): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $company->owner_id === $user->id || $user->hasRole('super-admin');
    }

    /**
     * @return array{data: \Illuminate\Database\Eloquent\Collection<int, Company>, current_page: int, last_page: int, per_page: int, total: int}
     */
    private function companiesIndexPayload(Builder $query): array
    {
        $list = $query->latest()->get();
        $total = $list->count();

        return [
            'data' => $list,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => max($total, 1),
            'total' => $total,
        ];
    }

    /**
     * Display a listing of the companies.
     */
    public function index(): Response
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            $companies = $this->companiesIndexPayload(Company::query());
        } else {
            $ownedCompanies = Company::where('owner_id', $user->id);

            if ($ownedCompanies->exists()) {
                $companies = $this->companiesIndexPayload($ownedCompanies);
            } elseif ($this->canViewCompanyReadOnly()) {
                $roleService = app(\App\Services\EmployeeUserRoleService::class);
                $allowedCompanyIds = $roleService->companyIdsWhereCan($user, ['company.readonly']);
                $companies = $this->companiesIndexPayload(
                    Company::query()
                        ->when(
                            ! empty($allowedCompanyIds),
                            fn ($q) => $q->whereIn('id', $allowedCompanyIds),
                            fn ($q) => $q->whereRaw('1 = 0')
                        )
                );
            } else {
                abort(403);
            }
        }

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'isReadOnly' => ! Company::where('owner_id', Auth::id())->exists() && ! $user->hasRole('super-admin'),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        $user = Auth::user();
        $isReadOnlyOnly = $this->canViewCompanyReadOnly()
            && ! $user?->hasRole('super-admin')
            && ! Company::where('owner_id', $user?->id)->exists();

        abort_if($isReadOnlyOnly, 403);

        return Inertia::render('Companies/Create');
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $isReadOnlyOnly = $this->canViewCompanyReadOnly()
            && ! $user?->hasRole('super-admin')
            && ! Company::where('owner_id', $user?->id)->exists();

        abort_if($isReadOnlyOnly, 403);

        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'company_email' => 'required|email|unique:companies,company_email',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['owner_id'] = Auth::id();
        $validated['slug'] = Str::slug($validated['name_en']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::create($validated);

        app(SalaryRunApprovalService::class)->seedDefaultStepsForCompany($company);
        app(LeaveApprovalService::class)->seedDefaultStepsForCompany($company);
        app(SalaryCertificateApprovalService::class)->seedDefaultStepsForCompany($company);
        app(EntitlementSettlementApprovalService::class)->seedDefaultStepsForCompany($company);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): Response
    {
        $canManage = $this->canManageCompany($company);
        if (! $canManage) {
            abort_unless(
                $this->canViewCompanyReadOnly() && $this->canViewCompanyByTeamScope($company),
                403
            );
        }

        // Load the company with owner and Bayzat configuration
        $company->load(['owner', 'bayzatConfig']);

        // Get total assets count from all locations associated with this company
        $totalAssetsCount = $company->locations()
            ->withCount('assets')
            ->get()
            ->sum('assets_count');

        return Inertia::render('Companies/Show', [
            'company' => $company,
            'totalAssetsCount' => $totalAssetsCount,
            'isReadOnly' => ! $canManage,
            'capabilities' => $this->companyCapabilityFlags($company),
        ]);
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit(Company $company): Response
    {
        abort_unless($this->canManageCompany($company), 403);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the specified company in storage.
     */
    public function update(Request $request, Company $company)
    {
        abort_unless($this->canManageCompany($company), 403);

        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'company_email' => 'required|email|unique:companies,company_email,'.$company->id,
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'fingerprint_report_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        // Update slug if English name changed
        if ($validated['name_en'] !== $company->name_en) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        if ($request->boolean('remove_logo') && ! empty($company->logo)) {
            Storage::disk('public')->delete($company->logo);
            $validated['logo'] = null;
        }

        if ($request->hasFile('logo')) {
            if (! empty($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        unset($validated['remove_logo']);

        $company->update($validated);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy(Company $company)
    {
        abort_unless($this->canManageCompany($company), 403);

        if (! empty($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Search companies for async selection.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = $request->get('q', '');

        $companyQuery = Company::query();
        if ($user->hasRole('super-admin')) {
            // no extra filter
        } elseif (Company::where('owner_id', $user->id)->exists()) {
            $companyQuery->where('owner_id', $user->id);
        } elseif ($this->canViewCompanyReadOnly()) {
            $allowedCompanyIds = app(\App\Services\EmployeeUserRoleService::class)
                ->companyIdsWhereCan($user, ['company.readonly']);
            $companyQuery->when(
                ! empty($allowedCompanyIds),
                fn ($q) => $q->whereIn('id', $allowedCompanyIds),
                fn ($q) => $q->whereRaw('1 = 0')
            );
        } else {
            $companyQuery->whereRaw('1 = 0');
        }

        $companies = $companyQuery
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_en', 'like', "%{$query}%")
                        ->orWhere('name_ar', 'like', "%{$query}%")
                        ->orWhere('company_email', 'like', "%{$query}%");
                });
            })
            ->orderBy('name_en')
            ->limit(20)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name_en' => $company->name_en,
                    'name_ar' => $company->name_ar,
                    'company_email' => $company->company_email,
                    'display_name' => $company->name_en.($company->name_ar ? " ({$company->name_ar})" : ''),
                ];
            });

        return response()->json($companies);
    }
}
