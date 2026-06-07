<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;

class AttendancePenaltyAutoApprovalService
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private AttendancePenaltyApprovalNotifier $approvalNotifier,
        private OperationalMonthService $operationalMonthService,
    ) {}

    public function isEnabledForCompany(int $companyId): bool
    {
        $company = Company::query()->find($companyId);

        return $company !== null && $company->autoApproveAttendancePenalties();
    }

    public function isEnabledForEmployee(int $employeeId): bool
    {
        $companyId = Employee::query()->whereKey($employeeId)->value('company_id');

        return $companyId !== null && $this->isEnabledForCompany((int) $companyId);
    }

    /**
     * Auto-approve pending late penalty when company policy allows it
     * and the attendance date falls within the current operational month.
     */
    public function applyForPenalty(AttendancePenalty $penalty): AttendancePenalty
    {
        if ($penalty->approval_status !== 'pending') {
            return $penalty;
        }

        if (! $penalty->isLateViolation()) {
            return $penalty;
        }

        if (! $this->isEnabledForEmployee((int) $penalty->employee_id)) {
            return $penalty;
        }

        if (! $this->isWithinCurrentOperationalMonth($penalty)) {
            return $penalty;
        }

        $penalty->update([
            'approval_status' => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
        ]);

        $penalty = $penalty->fresh();
        $this->approvalNotifier->notifyEmployeeOfApproval($penalty);

        return $penalty;
    }

    /**
     * Toggle company policy; when enabling, approve pending late penalties
     * within the current operational month for its employees.
     *
     * @return int Number of penalties auto-approved when enabling
     */
    public function setEnabledForCompany(Company $company, bool $enabled): int
    {
        $wasEnabled = $company->autoApproveAttendancePenalties();
        $company->setSetting(Company::SETTING_AUTO_APPROVE_PENALTIES, $enabled);

        if ($enabled && ! $wasEnabled) {
            return $this->approveAllPendingForCompany((int) $company->id);
        }

        return 0;
    }

    public function approveAllPendingForCompany(int $companyId): int
    {
        $employeeIds = Employee::query()
            ->where('company_id', $companyId)
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return 0;
        }

        [$periodStart, $periodEnd] = $this->currentOperationalMonthDateBounds();

        $pendingPenalties = AttendancePenalty::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('approval_status', 'pending')
            ->lateViolations()
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->get();

        $approvedCount = 0;

        foreach ($pendingPenalties as $penalty) {
            $penalty->update([
                'approval_status' => 'approved',
                'approved_by' => null,
                'approved_at' => now(),
            ]);

            $this->approvalNotifier->notifyEmployeeOfApproval($penalty->fresh());
            $approvedCount++;
        }

        return $approvedCount;
    }

    /**
     * @return array{0: string, 1: string} [startYmd, endYmd]
     */
    private function currentOperationalMonthDateBounds(): array
    {
        $range = $this->operationalMonthService->resolveCurrentOperationalMonthRange(
            Carbon::now(self::TZ)
        );

        return [
            $range['start']->format('Y-m-d'),
            $range['end']->format('Y-m-d'),
        ];
    }

    private function isWithinCurrentOperationalMonth(AttendancePenalty $penalty): bool
    {
        $attDate = $penalty->attendance_date instanceof Carbon
            ? $penalty->attendance_date->format('Y-m-d')
            : Carbon::parse((string) $penalty->attendance_date, self::TZ)->format('Y-m-d');

        [$periodStart, $periodEnd] = $this->currentOperationalMonthDateBounds();

        return $attDate >= $periodStart && $attDate <= $periodEnd;
    }
}
