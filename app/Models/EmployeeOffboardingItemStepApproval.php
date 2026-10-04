<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOffboardingItemStepApproval extends Model
{
    protected $fillable = [
        'offboarding_item_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function offboardingItem(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboardingItem::class, 'offboarding_item_id');
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(OffboardingItemApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
