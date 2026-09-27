<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdvanceEntitlementTier extends Model
{
    protected $fillable = [
        'min_months',
        'max_months',
        'max_amount',
        'max_installments',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_months' => 'integer',
        'max_months' => 'integer',
        'max_amount' => 'decimal:2',
        'max_installments' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('min_months');
    }

    public function matchesTenure(int $monthsOfService): bool
    {
        if ($monthsOfService < $this->min_months) {
            return false;
        }

        if ($this->max_months === null) {
            return true;
        }

        return $monthsOfService <= $this->max_months;
    }
}
