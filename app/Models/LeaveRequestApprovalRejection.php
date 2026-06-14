<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestApprovalRejection extends Model
{
    protected $fillable = [
        'leave_request_id',
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

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalStep::class, 'approval_step_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
