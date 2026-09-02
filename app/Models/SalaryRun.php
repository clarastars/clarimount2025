<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryRun extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const RUN_TYPE_REGULAR = 'regular';

    public const RUN_TYPE_SUPPLEMENTARY = 'supplementary';

    protected $fillable = [
        'company_id',
        'year',
        'month',
        'run_type',
        'sequence',
        'label',
        'status',
        'created_by',
        'hr_approved_at',
        'hr_approved_by',
        'director_approved_at',
        'director_approved_by',
        'financial_manager_approved_at',
        'financial_manager_approved_by',
        'accountant_approved_at',
        'accountant_approved_by',
        'ceo_approved_at',
        'ceo_approved_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'sequence' => 'integer',
        'hr_approved_at' => 'datetime',
        'director_approved_at' => 'datetime',
        'financial_manager_approved_at' => 'datetime',
        'accountant_approved_at' => 'datetime',
        'ceo_approved_at' => 'datetime',
    ];

    /**
     * Get the company this salary run belongs to
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created this salary run
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approverHr(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function approverDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_approved_by');
    }

    public function approverAccountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_approved_by');
    }

    public function approverFinancialManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_manager_approved_by');
    }

    public function approverCeo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_approved_by');
    }

    /**
     * Get all salary run items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryRunItem::class);
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(SalaryRunStepApproval::class);
    }

    public function approvalRejections(): HasMany
    {
        return $this->hasMany(SalaryRunApprovalRejection::class);
    }

    /**
     * Check if salary run is finalized
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * Check if salary run is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRegular(): bool
    {
        return ($this->run_type ?? self::RUN_TYPE_REGULAR) === self::RUN_TYPE_REGULAR;
    }

    public function isSupplementary(): bool
    {
        return $this->run_type === self::RUN_TYPE_SUPPLEMENTARY;
    }

    public function displayTitle(): string
    {
        if ($this->isSupplementary() && filled($this->label)) {
            return (string) $this->label;
        }

        return __('messages.salary_runs.run_type_regular');
    }
}
