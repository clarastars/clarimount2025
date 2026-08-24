<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveTypeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeOptions(?string $locale = null): array
    {
        return LeaveType::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (LeaveType $leaveType): array => $this->mapOption($leaveType, $locale))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForSettings(): array
    {
        return LeaveType::query()
            ->ordered()
            ->get()
            ->map(fn (LeaveType $leaveType): array => [
                'id' => $leaveType->id,
                'key' => $leaveType->key,
                'name_en' => $leaveType->name_en,
                'name_ar' => $leaveType->name_ar,
                'min_notice_days' => $leaveType->min_notice_days,
                'allow_past_dates' => (bool) $leaveType->allow_past_dates,
                'sort_order' => $leaveType->sort_order,
                'is_active' => $leaveType->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function activeKeys(): array
    {
        return LeaveType::query()
            ->active()
            ->ordered()
            ->pluck('key')
            ->values()
            ->all();
    }

    public function findActiveByKey(string $key): ?LeaveType
    {
        return LeaveType::query()
            ->active()
            ->where('key', $key)
            ->first();
    }

    public function findByKey(string $key): ?LeaveType
    {
        return LeaveType::query()
            ->withTrashed()
            ->where('key', $key)
            ->first();
    }

    public function labelForKey(?string $key, ?string $locale = null): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        $leaveType = $this->findByKey($key);

        return $leaveType?->displayName($locale) ?? $key;
    }

    public function ensureStartDateAllowed(LeaveType $leaveType, string $startDate): void
    {
        if ($leaveType->allow_past_dates) {
            return;
        }

        $today = now()->startOfDay();
        $requestedStart = Carbon::parse($startDate)->startOfDay();

        if ($requestedStart->lt($today)) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.start_date_must_be_today_or_later')],
            ]);
        }
    }

    public function ensureMinimumNoticeDays(LeaveType $leaveType, string $startDate): void
    {
        $minNoticeDays = max(0, (int) $leaveType->min_notice_days);

        if ($minNoticeDays === 0) {
            return;
        }

        $today = now()->startOfDay();
        $requestedStart = Carbon::parse($startDate)->startOfDay();

        // Past-dated requests are governed by allow_past_dates; skip advance-notice rules for them.
        if ($leaveType->allow_past_dates && $requestedStart->lt($today)) {
            return;
        }

        $noticeDays = (int) $today->diffInDays($requestedStart, false);

        if ($noticeDays < $minNoticeDays) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.min_notice_days_not_met', [
                    'leave_type' => $leaveType->displayName(),
                    'days' => $minNoticeDays,
                ])],
            ]);
        }
    }

    public function buildKeyFromName(string $name): string
    {
        $normalized = trim(Str::of($name)->ascii()->lower()->slug('-')->value());

        return $normalized !== '' ? $normalized : 'leave-type';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOption(LeaveType $leaveType, ?string $locale = null): array
    {
        return [
            'key' => $leaveType->key,
            'name_en' => $leaveType->name_en,
            'name_ar' => $leaveType->name_ar,
            'label' => $leaveType->displayName($locale),
            'min_notice_days' => $leaveType->min_notice_days,
            'allow_past_dates' => (bool) $leaveType->allow_past_dates,
        ];
    }
}
