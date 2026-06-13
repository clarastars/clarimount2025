<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePortalLeaveController extends Controller
{
    public function __construct(
        private LeaveRequestService $leaveRequestService,
    ) {}

    public function index(): Response|RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        if ($employee === null) {
            return redirect()->route('dashboard');
        }

        $employee->load(['company']);
        $employee->append('remaining_annual_leave_balance');

        $approvedLeaves = $employee->leaves()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Leave $leave): array => $this->mapLeave($leave))
            ->values()
            ->all();

        $leaveRequests = $employee->leaveRequests()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeaveRequest $request): array => $this->mapLeaveRequest($request))
            ->values()
            ->all();

        return Inertia::render('Employee/Leaves', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'annual_leave_balance' => $employee->annual_leave_balance,
                'leave_accrued_balance' => $employee->leave_accrued_balance,
                'remaining_annual_leave_balance' => $employee->remaining_annual_leave_balance,
                'monthly_leave_accrual' => $employee->monthlyLeaveAccrualDays(),
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
            ],
            'approvedLeaves' => $approvedLeaves,
            'leaveRequests' => $leaveRequests,
            'leaveTypes' => Leave::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->leaveRequestService->submitForEmployee($employee, $request);

        return redirect()
            ->route('employee.leaves.index')
            ->with('success', __('messages.leaves.request_submitted_success'));
    }

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->leaveRequestService->cancelByEmployee($leaveRequest, $employee);

        return redirect()
            ->route('employee.leaves.index')
            ->with('success', __('messages.leaves.request_cancelled_success'));
    }

    private function resolvePortalEmployee()
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        if (! $this->isEmployeePortalUser($user)) {
            return null;
        }

        return $user->employee;
    }

    private function isEmployeePortalUser(User $user): bool
    {
        return $user->roles()->where('name', 'employee')->exists() || $user->employee()->exists();
    }

    private function mapLeave(Leave $leave): array
    {
        return [
            'id' => $leave->id,
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date->format('Y-m-d'),
            'end_date' => $leave->end_date->format('Y-m-d'),
            'days' => $leave->days,
            'deduct_from_balance' => $leave->deduct_from_balance,
            'is_paid' => $leave->is_paid,
            'notes' => $leave->notes,
        ];
    }

    private function mapLeaveRequest(LeaveRequest $leaveRequest): array
    {
        return [
            'id' => $leaveRequest->id,
            'leave_type' => $leaveRequest->leave_type,
            'start_date' => $leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date->format('Y-m-d'),
            'days' => $leaveRequest->days,
            'deduct_from_balance' => $leaveRequest->deduct_from_balance,
            'is_paid' => $leaveRequest->is_paid,
            'notes' => $leaveRequest->notes,
            'status' => $leaveRequest->status,
            'review_notes' => $leaveRequest->review_notes,
            'created_at' => $leaveRequest->created_at?->toIso8601String(),
            'reviewed_at' => $leaveRequest->reviewed_at?->toIso8601String(),
        ];
    }
}
