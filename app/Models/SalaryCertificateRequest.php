<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryCertificateRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const LANGUAGES = ['ar', 'en', 'both'];

    protected $fillable = [
        'employee_id',
        'purpose',
        'addressed_to',
        'language',
        'notes',
        'status',
        'certificate_path',
        'certificate_disk',
        'certificate_name',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(SalaryCertificateRequestStepApproval::class);
    }

    public function approvalRejections(): HasMany
    {
        return $this->hasMany(SalaryCertificateRequestApprovalRejection::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Older records were stored on the local public disk before cloud storage was adopted.
     */
    public function certificateDisk(): string
    {
        return $this->certificate_disk ?: 'public';
    }

    public function certificateDownloadName(): string
    {
        return $this->certificate_name ?: 'salary-certificate-'.$this->id.'.pdf';
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
