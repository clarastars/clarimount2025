<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntitlementSettlementStepApproval extends Model
{
    protected $fillable = [
        'settlement_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EmployeeEntitlementSettlement::class, 'settlement_id');
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(EntitlementSettlementApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
