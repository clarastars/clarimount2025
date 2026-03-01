<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';
    public const TYPE_MARRIAGE = 'marriage';
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_MATERNITY = 'maternity';

    public const TYPES = [
        self::TYPE_ANNUAL,
        self::TYPE_SICK,
        self::TYPE_MARRIAGE,
        self::TYPE_EMERGENCY,
        self::TYPE_MATERNITY,
    ];

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'deduct_from_balance',
        'is_paid',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'integer',
        'deduct_from_balance' => 'boolean',
        'is_paid' => 'boolean',
    ];

    /**
     * Get the employee this leave belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Check if this leave overlaps with a given month (year, month).
     */
    public function overlapsMonth(int $year, int $month): bool
    {
        /** @var \Carbon\Carbon $start */
        $start = $this->start_date;
        /** @var \Carbon\Carbon $end */
        $end = $this->end_date;
        $monthStart = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        return $start->lte($monthEnd) && $end->gte($monthStart);
    }

    /**
     * Get number of days of this leave that fall within a given month.
     */
    public function daysInMonth(int $year, int $month): int
    {
        $monthStart = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        /** @var \Carbon\Carbon $startDate */
        $startDate = $this->start_date;
        /** @var \Carbon\Carbon $endDate */
        $endDate = $this->end_date;
        $overlapStart = $startDate->copy()->max($monthStart);
        $overlapEnd = $endDate->copy()->min($monthEnd);

        if ($overlapStart->gt($overlapEnd)) {
            return 0;
        }

        return $overlapStart->diffInDays($overlapEnd) + 1;
    }
}
