<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name_en',
        'name_ar',
        'min_notice_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_notice_days' => 'integer',
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
            ->orderBy('name_en');
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ar') {
            return $this->name_ar !== '' ? $this->name_ar : $this->name_en;
        }

        return $this->name_en !== '' ? $this->name_en : $this->name_ar;
    }
}
