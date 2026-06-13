<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\Leave;
use App\Services\LeaveStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private LeaveStoreService $leaveStoreService,
    ) {}

    public function create(Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanCreateLeaveForEmployee($user, $employee);

        $employee->load(['company']);

        return Inertia::render('Employees/LeaveCreate', [
            'employee' => $employee,
            'leaveTypes' => Leave::TYPES,
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanCreateLeaveForEmployee($user, $employee);

        $this->leaveStoreService->validateAndCreate($request, $employee);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', __('messages.leaves.created_success'));
    }
}
