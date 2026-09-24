<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceRequestApprovalRejection extends Model
{
    protected $fillable = [
        'advance_request_id',
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

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(AdvanceRequest::class);
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(AdvanceApprovalStep::class, 'approval_step_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
