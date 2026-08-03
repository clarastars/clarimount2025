<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Services\EmployeeUserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartments($user), 403);

        $companyIds = $this->departmentManageableCompanyIds($user);

        $query = Department::with('company')
            ->withCount('employees')
            ->whereIn('company_id', $companyIds->isEmpty() ? [-1] : $companyIds);

        if ($request->filled('company_id')) {
            $companyId = (int) $request->company_id;
            if ($companyIds->contains($companyId)) {
                $query->where('company_id', $companyId);
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name_en', 'like', "%{$search}%")
                            ->orWhere('name_ar', 'like', "%{$search}%");
                    })
                    ->orWhereExists(function ($accessQuery) use ($search) {
                        $accessQuery->select(DB::raw(1))
                            ->from('company_user_access as cua')
                            ->join('teams', 'teams.id', '=', 'cua.team_id')
                            ->join('users', 'users.id', '=', 'cua.user_id')
                            ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
                            ->whereColumn('cua.department_id', 'departments.id')
                            ->where(function ($inner) use ($search) {
                                $inner->where('teams.name', 'like', "%{$search}%")
                                    ->orWhere('users.name', 'like', "%{$search}%")
                                    ->orWhere('employees.first_name', 'like', "%{$search}%")
                                    ->orWhere('employees.father_name', 'like', "%{$search}%")
                                    ->orWhere('employees.last_name', 'like', "%{$search}%")
                                    ->orWhere('employees.employee_id', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $departments = $query->orderBy('code')->paginate(15)->withQueryString();

        $roleAssigneesByDepartment = $this->roleAssigneesByDepartmentIds(
            $departments->getCollection()->pluck('id')
        );

        $departments->getCollection()->transform(function (Department $department) use ($roleAssigneesByDepartment) {
            $roleAssignees = $roleAssigneesByDepartment->get((string) $department->id, collect())->values()->all();

            return [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'company_id' => $department->company_id,
                'employees_count' => $department->employees_count,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
                'company' => $department->company ? [
                    'id' => $department->company->id,
                    'name_en' => $department->company->name_en,
                    'name_ar' => $department->company->name_ar,
                    'company_email' => $department->company->company_email,
                ] : null,
                'role_assignees' => $roleAssignees,
            ];
        });

        $companies = Company::query()
            ->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)
            ->orderBy('name_en')
            ->get();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'companies' => $companies,
            'filters' => $request->only(['search', 'company_id']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartments($user), 403);

        $companyIds = $this->departmentManageableCompanyIds($user);
        $companies = Company::query()
            ->whereIn('id', $companyIds->isEmpty() ? [-1] : $companyIds)
            ->orderBy('name_en')
            ->get();

        return Inertia::render('Departments/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartments($user), 403);

        $companyIds = $this->departmentManageableCompanyIds($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => [
                'required',
                'exists:companies,id',
                function ($attribute, $value, $fail) use ($companyIds) {
                    if (! $companyIds->contains((int) $value)) {
                        $fail('You can only create departments for companies you can manage.');
                    }
                },
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('departments')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->company_id);
                }),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')->with('success', __('messages.departments.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): Response
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        $department->load([
            'company',
            'employees' => function ($query) {
                $query->orderBy('first_name')->orderBy('last_name');
            },
        ]);

        $employees = $department->employees->map(fn (Employee $employee) => $this->mapEmployeeSummary($employee))->values();

        $statusCounts = $department->employees
            ->groupBy(fn (Employee $employee) => $employee->employment_status ?: 'unknown')
            ->map->count();

        $roleAssignees = $this->roleAssigneesByDepartmentIds(collect([(string) $department->id]))
            ->get((string) $department->id, collect())
            ->values()
            ->all();

        return Inertia::render('Departments/Show', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'company_id' => $department->company_id,
                'created_at' => $department->created_at?->toIso8601String(),
                'updated_at' => $department->updated_at?->toIso8601String(),
                'company' => $department->company ? [
                    'id' => $department->company->id,
                    'name_en' => $department->company->name_en,
                    'name_ar' => $department->company->name_ar,
                    'company_email' => $department->company->company_email,
                ] : null,
                'role_assignees' => $roleAssignees,
                'employees' => $employees,
                'stats' => [
                    'employees_count' => $employees->count(),
                    'active_employees_count' => (int) ($statusCounts->get('active') ?? 0),
                    'role_assignees_count' => count($roleAssignees),
                    'status_counts' => $statusCounts,
                ],
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department): Response
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        return Inertia::render('Departments/Edit', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'company_id' => $department->company_id,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('departments')->where(function ($query) use ($department) {
                    return $query->where('company_id', $department->company_id);
                })->ignore($department->id),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', __('messages.departments.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        if ($department->employees()->count() > 0) {
            return back()->with('error', __('messages.departments.cannot_delete_with_employees'));
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', __('messages.departments.deleted_successfully'));
    }

    /**
     * Search departments for async selection.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return response()->json([]);
        }

        $query = $request->get('q', '');
        $companyId = $request->get('company_id');
        $companyIds = $this->departmentManageableCompanyIds($user);

        if ($companyIds->isEmpty()) {
            return response()->json([]);
        }

        $departments = Department::query()
            ->whereIn('company_id', $companyIds)
            ->when($companyId, function ($q) use ($companyId, $companyIds) {
                if ($companyIds->contains((int) $companyId)) {
                    return $q->where('company_id', $companyId);
                }

                return $q->whereIn('company_id', $companyIds);
            })
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                });
            })
            ->with('company')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'description' => $department->description,
                    'company_id' => $department->company_id,
                    'company_name' => $department->company->name_en,
                    'display_name' => "{$department->code}: {$department->name}",
                ];
            });

        return response()->json($departments);
    }

    private function canManageDepartments($user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return app(EmployeeUserRoleService::class)->canInAnyAssignedTeam($user, 'departments.manage');
    }

    private function canManageDepartment($user, Department $department): bool
    {
        return $this->departmentManageableCompanyIds($user)->contains((int) $department->company_id);
    }

    /**
     * @return Collection<int, int>
     */
    private function departmentManageableCompanyIds($user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id');
        }

        $ownedIds = $user->ownedCompanies()->pluck('id');
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds;
        }

        return collect(app(EmployeeUserRoleService::class)->companyIdsWhereCan($user, ['departments.manage']));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEmployeeSummary(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_id' => $employee->employee_id,
            'company_id' => $employee->company_id,
            'job_title' => $employee->job_title,
            'employment_status' => $employee->employment_status,
            'email' => $employee->work_email ?: $employee->personal_email,
        ];
    }

    /**
     * Users with a team role scoped specifically to these departments.
     *
     * @param  Collection<int, string>  $departmentIds
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function roleAssigneesByDepartmentIds(Collection $departmentIds): Collection
    {
        $ids = $departmentIds
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = DB::table('company_user_access as cua')
            ->join('teams', 'teams.id', '=', 'cua.team_id')
            ->join('users', 'users.id', '=', 'cua.user_id')
            ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
            ->whereIn('cua.department_id', $ids->all())
            ->whereNotNull('cua.team_id')
            ->orderBy('teams.name')
            ->orderBy('users.name')
            ->get([
                'cua.department_id',
                'cua.user_id',
                'cua.team_id',
                'teams.name as team_name',
                'users.name as user_name',
                'employees.id as employee_pk',
                'employees.first_name',
                'employees.father_name',
                'employees.last_name',
                'employees.employee_id as employee_code',
                'employees.job_title',
            ]);

        return $rows
            ->groupBy(fn ($row) => (string) $row->department_id)
            ->map(function (Collection $group): Collection {
                return $group
                    ->map(function ($row): array {
                        $fullName = trim(implode(' ', array_filter([
                            $row->first_name,
                            $row->father_name,
                            $row->last_name,
                        ])));

                        if ($fullName === '') {
                            $fullName = (string) $row->user_name;
                        }

                        return [
                            'user_id' => (int) $row->user_id,
                            'employee_id' => $row->employee_pk !== null ? (int) $row->employee_pk : null,
                            'full_name' => $fullName,
                            'employee_code' => $row->employee_code,
                            'job_title' => $row->job_title,
                            'team_id' => (int) $row->team_id,
                            'team_name' => (string) $row->team_name,
                            'source' => 'role',
                        ];
                    })
                    ->unique(fn (array $item): string => $item['user_id'].'-'.$item['team_id'])
                    ->values();
            });
    }
}
