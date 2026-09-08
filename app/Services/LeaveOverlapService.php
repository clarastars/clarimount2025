<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaveOverlapService
{
    public function __construct(
        private LeaveTypeService $leaveTypeService,
    ) {}

    /**
     * Overlapping company/department leaves for each pending request.
     *
     * @param  Collection<int, LeaveRequest>  $leaveRequests
     * @return array<int, array{department: list<array<string, mixed>>, company: list<array<string, mixed>>, total: int}>
     */
    public function forPendingRequests(Company $company, Collection $leaveRequests): array
    {
        if ($leaveRequests->isEmpty()) {
            return [];
        }

        $leaveRequests->loadMissing(['employee.department']);

        $minStart = $leaveRequests->min(fn (LeaveRequest $request): string => $request->start_date->toDateString());
        $maxEnd = $leaveRequests->max(fn (LeaveRequest $request): string => $request->end_date->toDateString());

        $approvedLeaves = Leave::query()
            ->whereHas('employee', fn ($query) => $query->where('company_id', $company->id))
            ->whereDate('start_date', '<=', $maxEnd)
            ->whereDate('end_date', '>=', $minStart)
            ->with(['employee:id,first_name,father_name,last_name,department_id,company_id', 'employee.department:id,name'])
            ->orderBy('start_date')
            ->get();

        $pendingRequests = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereHas('employee', fn ($query) => $query->where('company_id', $company->id))
            ->whereDate('start_date', '<=', $maxEnd)
            ->whereDate('end_date', '>=', $minStart)
            ->with(['employee:id,first_name,father_name,last_name,department_id,company_id', 'employee.department:id,name'])
            ->orderBy('start_date')
            ->get();

        $result = [];

        foreach ($leaveRequests as $request) {
            $result[(int) $request->id] = $this->groupOverlapsForRequest(
                $request,
                $approvedLeaves,
                $pendingRequests,
            );
        }

        return $result;
    }

    /**
     * @return array{department: list<array<string, mixed>>, company: list<array<string, mixed>>, total: int}
     */
    private function groupOverlapsForRequest(
        LeaveRequest $request,
        Collection $approvedLeaves,
        Collection $pendingRequests,
    ): array {
        $items = [];

        foreach ($approvedLeaves as $leave) {
            $row = $this->mapOverlap(
                $request,
                $leave->employee,
                $leave->leave_type,
                $leave->start_date,
                $leave->end_date,
                (int) $leave->days,
                'approved',
            );

            if ($row !== null) {
                $items[] = $row;
            }
        }

        foreach ($pendingRequests as $otherRequest) {
            if ((int) $otherRequest->id === (int) $request->id) {
                continue;
            }

            $row = $this->mapOverlap(
                $request,
                $otherRequest->employee,
                $otherRequest->leave_type,
                $otherRequest->start_date,
                $otherRequest->end_date,
                (int) $otherRequest->days,
                'pending',
            );

            if ($row !== null) {
                $items[] = $row;
            }
        }

        $departmentId = $request->employee?->department_id !== null
            ? (string) $request->employee->department_id
            : null;

        $department = [];
        $company = [];

        foreach ($items as $item) {
            if ($departmentId !== null && $item['department_id'] === $departmentId) {
                $department[] = $item;
            } else {
                $company[] = $item;
            }
        }

        return [
            'department' => $department,
            'company' => $company,
            'total' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapOverlap(
        LeaveRequest $request,
        ?Employee $employee,
        ?string $leaveType,
        mixed $startDate,
        mixed $endDate,
        int $days,
        string $status,
    ): ?array {
        if ($employee === null || (int) $employee->id === (int) $request->employee_id) {
            return null;
        }

        $otherStart = $this->asDate($startDate);
        $otherEnd = $this->asDate($endDate);
        $requestStart = $this->asDate($request->start_date);
        $requestEnd = $this->asDate($request->end_date);

        if ($otherStart === null || $otherEnd === null || $requestStart === null || $requestEnd === null) {
            return null;
        }

        if ($otherStart->gt($requestEnd) || $otherEnd->lt($requestStart)) {
            return null;
        }

        $overlapStart = $otherStart->greaterThan($requestStart) ? $otherStart : $requestStart;
        $overlapEnd = $otherEnd->lessThan($requestEnd) ? $otherEnd : $requestEnd;
        $overlapDays = (int) $overlapStart->diffInDays($overlapEnd) + 1;

        return [
            'employee_id' => (int) $employee->id,
            'employee_name' => $employee->full_name,
            'department_id' => $employee->department_id !== null ? (string) $employee->department_id : null,
            'department_name' => $employee->department?->name,
            'leave_type' => $leaveType,
            'leave_type_label' => $this->leaveTypeService->labelForKey($leaveType),
            'start_date' => $otherStart->toDateString(),
            'end_date' => $otherEnd->toDateString(),
            'days' => $days,
            'overlap_days' => $overlapDays,
            'status' => $status,
        ];
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->startOfDay();
    }
}
