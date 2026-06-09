<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;

class EmployeeFingerprintMonthSyncService
{
    public function __construct(
        private readonly FingerprintIclockAttendanceService $fingerprintService,
        private readonly AttendancePresentationRebuildService $presentationRebuild,
        private readonly OperationalMonthService $operationalMonthService,
    ) {
    }

    public function syncForEmployee(Employee $employee): void
    {
        $employeeId = (int) $employee->id;

        $this->fingerprintService->setProgressEnabled(true);
        $this->fingerprintService->syncCurrentMonthUntilTodayForEmployeeId($employeeId);

        $now = Carbon::now('Asia/Riyadh');
        $operationalRange = $this->operationalMonthService->resolveCurrentOperationalMonthRange($now);
        $start = $operationalRange['start']->format('Y-m-d');
        $end = Carbon::today('Asia/Riyadh')
            ->min($operationalRange['end']->copy()->startOfDay())
            ->format('Y-m-d');

        $this->presentationRebuild->rebuildCompanyDateRange((int) $employee->company_id, $start, $end);
    }
}
