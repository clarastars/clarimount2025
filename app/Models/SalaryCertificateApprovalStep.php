<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryCertificateApprovalStep extends Model
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
        return $this->hasMany(SalaryCertificateRequestStepApproval::class, 'approval_step_id');
    }

    public function stepRejections(): HasMany
    {
        return $this->hasMany(SalaryCertificateRequestApprovalRejection::class, 'approval_step_id');
    }

    public function hasBlockingWorkflowUsage(): bool
    {
        $pendingConstraint = static function ($query): void {
            $query->where('status', SalaryCertificateRequest::STATUS_PENDING);
        };

        if ($this->stepApprovals()->whereHas('salaryCertificateRequest', $pendingConstraint)->exists()) {
            return true;
        }

        return $this->stepRejections()
            ->whereHas('salaryCertificateRequest', $pendingConstraint)
            ->exists();
    }
}
