<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOffboardingCase extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING_CLEARANCE = 'pending_clearance';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'company_id',
        'status',
        'started_by',
        'started_at',
        'cleared_at',
        'termination_date',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cleared_at' => 'datetime',
        'termination_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItem::class, 'offboarding_case_id');
    }

    public function clearanceStepApprovals(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingClearanceStepApproval::class, 'offboarding_case_id');
    }

    public function clearanceRejections(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingClearanceApprovalRejection::class, 'offboarding_case_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPendingClearance(): bool
    {
        return $this->status === self::STATUS_PENDING_CLEARANCE;
    }

    public function isCleared(): bool
    {
        return $this->status === self::STATUS_CLEARED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_IN_PROGRESS, self::STATUS_PENDING_CLEARANCE], true);
    }

    public function allItemsApproved(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(fn (EmployeeOffboardingItem $item) => $item->isApproved());
    }
}
