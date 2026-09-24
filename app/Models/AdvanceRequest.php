<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvanceRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'employee_id',
        'amount',
        'monthly_deduction',
        'reason',
        'months_count',
        'repayment_schedule',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'employee_debt_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'monthly_deduction' => 'decimal:2',
        'months_count' => 'integer',
        'repayment_schedule' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function employeeDebt(): BelongsTo
    {
        return $this->belongsTo(EmployeeDebt::class, 'employee_debt_id');
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(AdvanceRequestStepApproval::class);
    }

    public function approvalRejections(): HasMany
    {
        return $this->hasMany(AdvanceRequestApprovalRejection::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * @return list<array{month_index: int, amount: float}>
     */
    public function schedule(): array
    {
        $schedule = $this->repayment_schedule;

        if (! is_array($schedule)) {
            return [];
        }

        return array_values(array_map(static fn (array $row): array => [
            'month_index' => (int) ($row['month_index'] ?? 0),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
        ], array_filter($schedule, 'is_array')));
    }
}
