<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOffboardingItemApprovalRejection extends Model
{
    protected $fillable = [
        'offboarding_item_id',
        'approval_step_id',
        'rejected_at',
        'rejected_by',
        'reason',
        'cleared_approvals_count',
    ];

    protected $casts = [
        'rejected_at' => 'datetime',
        'cleared_approvals_count' => 'integer',
    ];

    public function offboardingItem(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboardingItem::class, 'offboarding_item_id');
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(OffboardingItemApprovalStep::class, 'approval_step_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
