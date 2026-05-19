<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryRunStepApproval extends Model
{
    protected $fillable = [
        'salary_run_id',
        'approval_step_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function salaryRun(): BelongsTo
    {
        return $this->belongsTo(SalaryRun::class);
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(SalaryRunApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
