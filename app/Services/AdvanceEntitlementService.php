<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdvanceEntitlementTier;
use App\Models\AdvanceRequest;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdvanceEntitlementService
{
    public const TZ = 'Asia/Riyadh';

    /**
     * @return Collection<int, AdvanceEntitlementTier>
     */
    public function allForSettings(): Collection
    {
        return AdvanceEntitlementTier::query()
            ->ordered()
            ->get()
            ->map(fn (AdvanceEntitlementTier $tier): array => $this->mapTier($tier))
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeTiersPayload(): array
    {
        return AdvanceEntitlementTier::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (AdvanceEntitlementTier $tier): array => $this->mapTier($tier))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapTier(AdvanceEntitlementTier $tier): array
    {
        return [
            'id' => $tier->id,
            'min_months' => (int) $tier->min_months,
            'max_months' => $tier->max_months !== null ? (int) $tier->max_months : null,
            'max_amount' => round((float) $tier->max_amount, 2),
            'max_installments' => (int) $tier->max_installments,
            'sort_order' => (int) $tier->sort_order,
            'is_active' => (bool) $tier->is_active,
        ];
    }

    public function monthsOfService(Employee $employee, ?Carbon $asOf = null): ?int
    {
        $hireDate = $this->resolveHireDate($employee);
        if ($hireDate === null) {
            return null;
        }

        $asOf = ($asOf ?? now(self::TZ))->copy()->timezone(self::TZ)->startOfDay();

        if ($asOf->lt($hireDate)) {
            return 0;
        }

        return (int) $hireDate->diffInMonths($asOf);
    }

    public function resolveHireDate(Employee $employee): ?Carbon
    {
        if ($employee->hire_date === null) {
            return null;
        }

        if ($employee->hire_date instanceof Carbon) {
            return $employee->hire_date->copy()->timezone(self::TZ)->startOfDay();
        }

        return Carbon::parse((string) $employee->hire_date, self::TZ)->startOfDay();
    }

    public function resolveTierForMonths(int $monthsOfService): ?AdvanceEntitlementTier
    {
        // When adjacent ranges share a boundary month (e.g. 0–3 and 3–12),
        // prefer the tier with the higher min_months so month 3 uses the newer band.
        return AdvanceEntitlementTier::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (AdvanceEntitlementTier $tier): bool => $tier->matchesTenure($monthsOfService))
            ->sortByDesc(fn (AdvanceEntitlementTier $tier): int => (int) $tier->min_months)
            ->first();
    }

    /**
     * Hire-anniversary year window: [start, end) in Asia/Riyadh.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function anniversaryPeriod(Employee $employee, ?Carbon $asOf = null): ?array
    {
        $hireDate = $this->resolveHireDate($employee);
        if ($hireDate === null) {
            return null;
        }

        $asOf = ($asOf ?? now(self::TZ))->copy()->timezone(self::TZ)->startOfDay();
        $month = (int) $hireDate->month;
        $day = (int) $hireDate->day;

        $anniversaryThisYear = $this->safeAnniversaryDate((int) $asOf->year, $month, $day);

        if ($asOf->gte($anniversaryThisYear)) {
            $start = $anniversaryThisYear;
            $end = $this->safeAnniversaryDate((int) $asOf->year + 1, $month, $day);
        } else {
            $start = $this->safeAnniversaryDate((int) $asOf->year - 1, $month, $day);
            $end = $anniversaryThisYear;
        }

        return ['start' => $start, 'end' => $end];
    }

    public function usedAmountInCurrentPeriod(Employee $employee, ?Carbon $asOf = null): float
    {
        $period = $this->anniversaryPeriod($employee, $asOf);
        if ($period === null) {
            return 0.0;
        }

        $total = AdvanceRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [AdvanceRequest::STATUS_PENDING, AdvanceRequest::STATUS_APPROVED])
            ->where('created_at', '>=', $period['start']->copy()->timezone(config('app.timezone')))
            ->where('created_at', '<', $period['end']->copy()->timezone(config('app.timezone')))
            ->sum('amount');

        return round((float) $total, 2);
    }

    /**
     * Full entitlement snapshot for the employee portal / validation.
     *
     * @return array{
     *     has_hire_date: bool,
     *     hire_date: string|null,
     *     months_of_service: int|null,
     *     can_request: bool,
     *     block_reason: string|null,
     *     tier: array<string, mixed>|null,
     *     annual_max_amount: float,
     *     used_amount: float,
     *     remaining_amount: float,
     *     max_installments: int,
     *     period_start: string|null,
     *     period_end: string|null
     * }
     */
    public function entitlementFor(Employee $employee, ?Carbon $asOf = null): array
    {
        $hireDate = $this->resolveHireDate($employee);

        if ($hireDate === null) {
            return [
                'has_hire_date' => false,
                'hire_date' => null,
                'months_of_service' => null,
                'can_request' => false,
                'block_reason' => 'missing_hire_date',
                'tier' => null,
                'annual_max_amount' => 0.0,
                'used_amount' => 0.0,
                'remaining_amount' => 0.0,
                'max_installments' => 0,
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $months = $this->monthsOfService($employee, $asOf) ?? 0;
        $tier = $this->resolveTierForMonths($months);
        $period = $this->anniversaryPeriod($employee, $asOf);
        $used = $this->usedAmountInCurrentPeriod($employee, $asOf);

        if ($tier === null) {
            return [
                'has_hire_date' => true,
                'hire_date' => $hireDate->toDateString(),
                'months_of_service' => $months,
                'can_request' => false,
                'block_reason' => 'no_matching_tier',
                'tier' => null,
                'annual_max_amount' => 0.0,
                'used_amount' => $used,
                'remaining_amount' => 0.0,
                'max_installments' => 0,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->copy()->subDay()->toDateString(),
            ];
        }

        $annualMax = round((float) $tier->max_amount, 2);
        $remaining = round(max(0, $annualMax - $used), 2);
        $canRequest = $remaining > 0;

        return [
            'has_hire_date' => true,
            'hire_date' => $hireDate->toDateString(),
            'months_of_service' => $months,
            'can_request' => $canRequest,
            'block_reason' => $canRequest ? null : 'no_remaining_entitlement',
            'tier' => $this->mapTier($tier),
            'annual_max_amount' => $annualMax,
            'used_amount' => $used,
            'remaining_amount' => $remaining,
            'max_installments' => (int) $tier->max_installments,
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Ensure tiers do not overlap on tenure ranges.
     *
     * @throws ValidationException
     */
    public function assertNoOverlap(
        int $minMonths,
        ?int $maxMonths,
        ?int $ignoreId = null,
    ): void {
        if ($maxMonths !== null && $maxMonths < $minMonths) {
            throw ValidationException::withMessages([
                'max_months' => [__('messages.settings.advance_entitlement_max_months_invalid')],
            ]);
        }

        $others = AdvanceEntitlementTier::query()
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('is_active', true)
            ->get();

        foreach ($others as $other) {
            $otherMin = (int) $other->min_months;
            $otherMax = $other->max_months !== null ? (int) $other->max_months : null;

            if ($this->rangesOverlap($minMonths, $maxMonths, $otherMin, $otherMax)) {
                throw ValidationException::withMessages([
                    'min_months' => [__('messages.settings.advance_entitlement_ranges_overlap')],
                ]);
            }
        }
    }

    private function rangesOverlap(int $aMin, ?int $aMax, int $bMin, ?int $bMax): bool
    {
        $aEnd = $aMax ?? PHP_INT_MAX;
        $bEnd = $bMax ?? PHP_INT_MAX;

        // Disjoint closed intervals.
        if ($aMin > $bEnd || $bMin > $aEnd) {
            return false;
        }

        // Shared boundary only is allowed (e.g. 0–3 next to 3–12).
        // At the shared month, resolveTierForMonths prefers the higher min_months.
        if ($aEnd === $bMin || $bEnd === $aMin) {
            return false;
        }

        return true;
    }

    private function safeAnniversaryDate(int $year, int $month, int $day): Carbon
    {
        $maxDay = (int) Carbon::create($year, $month, 1, 0, 0, 0, self::TZ)->daysInMonth;
        $safeDay = min($day, $maxDay);

        return Carbon::create($year, $month, $safeDay, 0, 0, 0, self::TZ)->startOfDay();
    }
}
