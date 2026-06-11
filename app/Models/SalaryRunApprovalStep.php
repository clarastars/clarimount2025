<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRunApprovalStep extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'sort_order',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(SalaryRunStepApproval::class, 'approval_step_id');
    }

    public function stepRejections(): HasMany
    {
        return $this->hasMany(SalaryRunApprovalRejection::class, 'approval_step_id');
    }

    public function hasBlockingWorkflowUsage(): bool
    {
        $companyId = (int) $this->company_id;

        $openRunConstraint = static function ($query) use ($companyId) {
            $query->where('company_id', $companyId)
                ->where('status', '!=', 'finalized');
        };

        if ($this->stepApprovals()->whereHas('salaryRun', $openRunConstraint)->exists()) {
            return true;
        }

        return $this->stepRejections()->whereHas('salaryRun', $openRunConstraint)->exists();
    }
}
