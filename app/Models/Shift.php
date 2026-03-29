<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'grace_minutes' => 'integer',
    ];

    /**
     * Get the workdays for this shift
     */
    public function workdays(): HasMany
    {
        return $this->hasMany(ShiftWorkday::class, 'shift_id');
    }

    /**
     * Get the employees assigned to this shift
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'shift_id');
    }

    /**
     * Expected clock-in time (H:i:s) for a weekday (0=Sunday … 6=Saturday).
     * Uses this shift's default when the workday has no override.
     */
    public function effectiveStartTimeStringForWeekday(int $weekday): string
    {
        $workday = $this->resolveWorkdayForWeekday($weekday);
        if (
            $workday !== null
            && $workday->is_workday
            && $workday->start_time !== null
        ) {
            return $this->formatTimeColumn($workday->start_time);
        }

        return $this->formatTimeColumn($this->start_time);
    }

    /**
     * Expected clock-out time (H:i:s) for a weekday.
     */
    public function effectiveEndTimeStringForWeekday(int $weekday): string
    {
        $workday = $this->resolveWorkdayForWeekday($weekday);
        if (
            $workday !== null
            && $workday->is_workday
            && $workday->end_time !== null
        ) {
            return $this->formatTimeColumn($workday->end_time);
        }

        return $this->formatTimeColumn($this->end_time);
    }

    /**
     * Work duration in minutes for one weekday (handles end after midnight).
     */
    public function effectiveWorkMinutesForWeekday(int $weekday): int
    {
        return self::minutesBetweenTimeStrings(
            $this->effectiveStartTimeStringForWeekday($weekday),
            $this->effectiveEndTimeStringForWeekday($weekday)
        );
    }

    /**
     * Average work minutes per workday (for salary / minute-rate estimates when days differ).
     */
    public function averageWorkMinutesPerWorkday(): ?float
    {
        $this->loadMissing('workdays');
        $total = 0;
        $count = 0;
        foreach ($this->workdays as $wd) {
            if (! $wd->is_workday) {
                continue;
            }
            $minutes = $this->effectiveWorkMinutesForWeekday((int) $wd->weekday);
            if ($minutes <= 0) {
                return null;
            }
            $total += $minutes;
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        return $total / $count;
    }

    /**
     * @param  CarbonInterface|string|null  $value
     */
    private function formatTimeColumn(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }

    private function resolveWorkdayForWeekday(int $weekday): ?ShiftWorkday
    {
        if ($this->relationLoaded('workdays')) {
            return $this->workdays->firstWhere('weekday', $weekday);
        }

        return $this->workdays()->where('weekday', $weekday)->first();
    }

    /**
     * Minutes from start H:i:s to end H:i:s (same calendar day, or overnight if end is before start).
     */
    public static function minutesBetweenTimeStrings(string $startHms, string $endHms): int
    {
        $startParts = array_map('intval', explode(':', substr($startHms, 0, 8)));
        $endParts = array_map('intval', explode(':', substr($endHms, 0, 8)));
        $startMinutes = ($startParts[0] ?? 0) * 60 + ($startParts[1] ?? 0);
        $endMinutes = ($endParts[0] ?? 0) * 60 + ($endParts[1] ?? 0);

        if ($endMinutes > $startMinutes) {
            return $endMinutes - $startMinutes;
        }

        return (24 * 60 - $startMinutes) + $endMinutes;
    }
}
