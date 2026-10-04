<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OffboardingItemApprovalStep extends Model
{
    protected $fillable = [
        'company_id',
        'template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(OffboardingChecklistItemTemplate::class, 'template_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItemStepApproval::class, 'approval_step_id');
    }

    public function stepRejections(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItemApprovalRejection::class, 'approval_step_id');
    }

    public function hasBlockingWorkflowUsage(): bool
    {
        $pendingConstraint = static function ($query) {
            $query->where('status', EmployeeOffboardingItem::STATUS_PENDING)
                ->whereHas('offboardingCase', function ($caseQuery) {
                    $caseQuery->whereIn('status', [
                        EmployeeOffboardingCase::STATUS_IN_PROGRESS,
                        EmployeeOffboardingCase::STATUS_PENDING_CLEARANCE,
                    ]);
                });
        };

        if ($this->stepApprovals()->whereHas('offboardingItem', $pendingConstraint)->exists()) {
            return true;
        }

        return $this->stepRejections()
            ->whereHas('offboardingItem', $pendingConstraint)
            ->exists();
    }
}
