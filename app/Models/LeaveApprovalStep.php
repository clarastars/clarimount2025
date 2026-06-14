<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApprovalStep extends Model
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
        return $this->hasMany(LeaveRequestStepApproval::class, 'approval_step_id');
    }

    public function stepRejections(): HasMany
    {
        return $this->hasMany(LeaveRequestApprovalRejection::class, 'approval_step_id');
    }

    public function hasBlockingWorkflowUsage(): bool
    {
        $pendingConstraint = static function ($query) {
            $query->where('status', LeaveRequest::STATUS_PENDING);
        };

        if ($this->stepApprovals()->whereHas('leaveRequest', $pendingConstraint)->exists()) {
            return true;
        }

        return $this->stepRejections()->whereHas('leaveRequest', $pendingConstraint)->exists();
    }
}
