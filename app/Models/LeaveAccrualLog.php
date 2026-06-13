<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAccrualLog extends Model
{
    protected $fillable = [
        'employee_id',
        'accrual_period',
        'days_accrued',
        'annual_entitlement',
        'balance_after',
    ];

    protected $casts = [
        'days_accrued' => 'decimal:2',
        'annual_entitlement' => 'integer',
        'balance_after' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
