<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOffboardingItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'offboarding_case_id',
        'template_id',
        'title',
        'attachment_mode',
        'sort_order',
        'status',
        'attachment_path',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboardingCase::class, 'offboarding_case_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OffboardingChecklistItemTemplate::class, 'template_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItemStepApproval::class, 'offboarding_item_id');
    }

    public function rejections(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItemApprovalRejection::class, 'offboarding_item_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function requiresAttachment(): bool
    {
        return $this->attachment_mode === OffboardingChecklistItemTemplate::ATTACHMENT_REQUIRED;
    }

    public function allowsAttachment(): bool
    {
        return in_array($this->attachment_mode, [
            OffboardingChecklistItemTemplate::ATTACHMENT_OPTIONAL,
            OffboardingChecklistItemTemplate::ATTACHMENT_REQUIRED,
        ], true);
    }
}
