<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Services\EmployeeUserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
                    });
            });
        }

        $departments = $query->orderBy('code')->paginate(15)->withQueryString();

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

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): Response
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        $department->load(['company', 'employees']);

        return Inertia::render('Departments/Show', [
            'department' => $department,
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
            'department' => $department,
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

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null && $this->canManageDepartment($user, $department), 403);

        if ($department->employees()->count() > 0) {
            return back()->with('error', 'Cannot delete department that has employees assigned.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
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
}
