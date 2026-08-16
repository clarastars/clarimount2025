<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
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
        $futureAccrualDays = $this->leaveAccrualService->futureAccrualDaysUntil($employee, $asOfDate);
        $pendingDays = $this->pendingDeductibleDaysOnOrBefore($employee, $asOfDate, $excludeRequestId);
        $projectedAccrued = round($currentAccrued + $futureAccrualDays, 2);
        $projectedRemaining = max(0, round($currentRemaining + $futureAccrualDays - $pendingDays, 2));

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

    private function pendingDeductibleDaysOnOrBefore(Employee $employee, Carbon $asOf, ?int $excludeRequestId = null): float
    {
        if ($employee->id === null) {
            return 0.0;
        }

        return round((float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->where('deduct_from_balance', true)
            ->whereDate('start_date', '<=', $asOf->toDateString())
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->sum('days'), 2);
    }

    private function parseDate(Carbon|string $asOf): Carbon
    {
        if ($asOf instanceof Carbon) {
            return Carbon::createFromFormat('Y-m-d', $asOf->format('Y-m-d'), self::TZ)->startOfDay();
        }

        $raw = trim((string) $asOf);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches) === 1) {
            return Carbon::createFromFormat('Y-m-d', $matches[1], self::TZ)->startOfDay();
        }

        return Carbon::parse($raw, self::TZ)->startOfDay();
    }
}
