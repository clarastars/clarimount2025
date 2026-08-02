<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryCertificateRequestStepApproval extends Model
{
    protected $fillable = [
        'salary_certificate_request_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function salaryCertificateRequest(): BelongsTo
    {
        return $this->belongsTo(SalaryCertificateRequest::class);
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(SalaryCertificateApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
