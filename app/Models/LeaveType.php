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

    public const BALANCE_DEDUCT = 'deduct';

    public const BALANCE_NO_DEDUCT = 'no_deduct';

    public const BALANCE_EMPLOYEE = 'employee';

    public const PAY_PAID = 'paid';

    public const PAY_UNPAID = 'unpaid';

    public const PAY_EMPLOYEE = 'employee';

    protected $fillable = [
        'key',
        'name_en',
        'name_ar',
        'min_notice_days',
        'allow_past_dates',
        'balance_deduction_mode',
        'pay_mode',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_notice_days' => 'integer',
        'allow_past_dates' => 'boolean',
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

    public function resolvedDeductFromBalance(bool $requested): bool
    {
        return match ($this->balance_deduction_mode) {
            self::BALANCE_DEDUCT => true,
            self::BALANCE_NO_DEDUCT => false,
            default => $requested,
        };
    }

    public function resolvedIsPaid(bool $requested): bool
    {
        return match ($this->pay_mode) {
            self::PAY_PAID => true,
            self::PAY_UNPAID => false,
            default => $requested,
        };
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
