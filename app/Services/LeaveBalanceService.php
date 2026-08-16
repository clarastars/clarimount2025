<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private LeaveAccrualService $leaveAccrualService,
    ) {}

    /**
     * @return array{
     *     as_of: string,
     *     current_remaining: float,
     *     projected_accrued: float,
     *     projected_remaining: float,
     *     future_accrual_days: float,
     *     uses_future_accrual: bool
     * }
     */
    public function forecastForDate(Employee $employee, Carbon|string $asOf, ?int $excludeRequestId = null): array
    {
        $asOfDate = $this->parseDate($asOf);
        $employee->append('remaining_annual_leave_balance');

        $currentRemaining = round((float) $employee->remaining_annual_leave_balance, 2);
        $currentAccrued = round((float) ($employee->leave_accrued_balance ?? 0), 2);
        $projectedAccrued = $this->leaveAccrualService->projectedAccruedBalanceAsOf($employee, $asOfDate);
        $reservedDays = $this->reservedDaysAsOf($employee, $asOfDate, $excludeRequestId);
        $projectedRemaining = max(0, round($projectedAccrued - $reservedDays, 2));
        $futureAccrualDays = max(0, round($projectedAccrued - $currentAccrued, 2));

        return [
            'as_of' => $asOfDate->toDateString(),
            'current_remaining' => $currentRemaining,
            'projected_accrued' => $projectedAccrued,
            'projected_remaining' => $projectedRemaining,
            'future_accrual_days' => $futureAccrualDays,
            'uses_future_accrual' => $futureAccrualDays > 0,
        ];
    }

    /**
     * @return array{
     *     as_of: string,
     *     current_remaining: float,
     *     projected_accrued: float,
     *     projected_remaining: float,
     *     future_accrual_days: float,
     *     uses_future_accrual: bool
     * }
     */
    public function assertSufficientBalance(
        Employee $employee,
        Carbon|string $asOf,
        float $requestedDays,
        ?int $excludeRequestId = null,
    ): array {
        $forecast = $this->forecastForDate($employee, $asOf, $excludeRequestId);

        if ($forecast['projected_remaining'] < $requestedDays) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.insufficient_balance_at_start', [
                    'date' => $forecast['as_of'],
                    'remaining' => $forecast['projected_remaining'],
                    'requested' => $requestedDays,
                ])],
            ]);
        }

        return $forecast;
    }

    private function reservedDaysAsOf(Employee $employee, Carbon $asOf, ?int $excludeRequestId = null): float
    {
        $legacyUsed = (float) ($employee->leave_days_used ?? 0);

        $approvedDays = (float) Leave::query()
            ->where('employee_id', $employee->id)
            ->where('deduct_from_balance', true)
            ->whereDate('start_date', '<=', $asOf->toDateString())
            ->sum('days');

        $pendingDays = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->where('deduct_from_balance', true)
            ->whereDate('start_date', '<=', $asOf->toDateString())
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->sum('days');

        return round($legacyUsed + $approvedDays + $pendingDays, 2);
    }

    private function parseDate(Carbon|string $asOf): Carbon
    {
        if ($asOf instanceof Carbon) {
            return $asOf->copy()->timezone(self::TZ)->startOfDay();
        }

        return Carbon::parse($asOf, self::TZ)->startOfDay();
    }
}
