<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceRequestStepApproval extends Model
{
    protected $fillable = [
        'advance_request_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(AdvanceRequest::class);
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(AdvanceApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
