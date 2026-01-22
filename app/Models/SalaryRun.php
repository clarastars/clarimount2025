<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'year',
        'month',
        'status',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
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

    /**
     * Get all salary run items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryRunItem::class);
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
}
