<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOffboardingClearanceStepApproval extends Model
{
    protected $fillable = [
        'offboarding_case_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboardingCase::class, 'offboarding_case_id');
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(OffboardingClearanceApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
