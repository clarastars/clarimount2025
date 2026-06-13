<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use App\Services\LeaveStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyLeaveController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private LeaveStoreService $leaveStoreService,
        private LeaveRequestService $leaveRequestService,
    ) {}

    public function index(Company $company): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);

        $today = now()->toDateString();

        $currentLeaves = Leave::query()
            ->whereHas('employee', fn ($query) => $query->where('company_id', $company->id))
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with(['employee:id,first_name,father_name,last_name,company_id'])
            ->orderBy('end_date')
            ->get()
            ->map(fn (Leave $leave): array => [
                'id' => $leave->id,
                'leave_type' => $leave->leave_type,
                'start_date' => $leave->start_date->format('Y-m-d'),
                'end_date' => $leave->end_date->format('Y-m-d'),
                'days' => $leave->days,
                'is_paid' => $leave->is_paid,
                'deduct_from_balance' => $leave->deduct_from_balance,
                'employee' => [
                    'id' => $leave->employee->id,
                    'full_name' => $leave->employee->full_name,
                ],
            ])
            ->values()
            ->all();

        $pendingRequests = $this->getCompanyLeaveRequestsByStatus($company, LeaveRequest::STATUS_PENDING, 'created_at');
        $approvedRequests = $this->getCompanyLeaveRequestsByStatus($company, LeaveRequest::STATUS_APPROVED, 'reviewed_at', descending: true);
        $rejectedRequests = $this->getCompanyLeaveRequestsByStatus($company, LeaveRequest::STATUS_REJECTED, 'reviewed_at', descending: true);

        $canCreateLeaves = $this->canCreateLeaves($user);

        $employees = [];
        if ($canCreateLeaves) {
            $employees = Employee::query()
                ->where('company_id', $company->id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'father_name', 'last_name'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Companies/Leaves', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'currentLeaves' => $currentLeaves,
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
            'employees' => $employees,
            'canCreateLeaves' => $canCreateLeaves,
            'canReviewLeaveRequests' => $canCreateLeaves,
            'isReadOnly' => ! $canCreateLeaves,
            'leaveTypes' => Leave::TYPES,
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        abort_unless((int) $employee->company_id === (int) $company->id, 403);

        $this->leaveStoreService->validateAndCreate($request, $employee);

        return redirect()
            ->route('companies.leaves.index', $company)
            ->with('success', __('messages.leaves.created_success'));
    }

    public function approveRequest(Request $request, Company $company, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessLeaveRequestBelongsToCompany($leaveRequest, $company);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->leaveRequestService->approve($leaveRequest, $user, $validated['review_notes'] ?? null);

        return redirect()
            ->route('companies.leaves.index', $company)
            ->with('success', __('messages.leaves.request_approved_success'));
    }

    public function rejectRequest(Request $request, Company $company, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessLeaveRequestBelongsToCompany($leaveRequest, $company);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->leaveRequestService->reject($leaveRequest, $user, $validated['review_notes'] ?? null);

        return redirect()
            ->route('companies.leaves.index', $company)
            ->with('success', __('messages.leaves.request_rejected_success'));
    }

    private function abortUnlessLeaveRequestBelongsToCompany(LeaveRequest $leaveRequest, Company $company): void
    {
        abort_unless(
            (int) $leaveRequest->employee()->value('company_id') === (int) $company->id,
            404
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCompanyLeaveRequestsByStatus(
        Company $company,
        string $status,
        string $orderColumn,
        bool $descending = false,
    ): array {
        $query = LeaveRequest::query()
            ->where('status', $status)
            ->whereHas('employee', fn ($query) => $query->where('company_id', $company->id))
            ->with([
                'employee:id,first_name,father_name,last_name,company_id',
                'reviewer:id,name',
            ]);

        if ($descending) {
            $query->orderByDesc($orderColumn);
        } else {
            $query->orderBy($orderColumn);
        }

        return $query
            ->get()
            ->map(fn (LeaveRequest $leaveRequest): array => $this->mapLeaveRequest($leaveRequest))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLeaveRequest(LeaveRequest $leaveRequest): array
    {
        return [
            'id' => $leaveRequest->id,
            'leave_type' => $leaveRequest->leave_type,
            'start_date' => $leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date->format('Y-m-d'),
            'days' => $leaveRequest->days,
            'is_paid' => $leaveRequest->is_paid,
            'deduct_from_balance' => $leaveRequest->deduct_from_balance,
            'notes' => $leaveRequest->notes,
            'status' => $leaveRequest->status,
            'review_notes' => $leaveRequest->review_notes,
            'attachment_url' => $leaveRequest->attachment_path
                ? Storage::disk('public')->url($leaveRequest->attachment_path)
                : null,
            'created_at' => $leaveRequest->created_at?->toIso8601String(),
            'reviewed_at' => $leaveRequest->reviewed_at?->toIso8601String(),
            'reviewer_name' => $leaveRequest->reviewer?->name,
            'employee' => [
                'id' => $leaveRequest->employee->id,
                'full_name' => $leaveRequest->employee->full_name,
            ],
        ];
    }
}
