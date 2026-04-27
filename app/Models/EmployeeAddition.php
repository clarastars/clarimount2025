<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAddition extends Model
{
    public const TYPE_MONTHLY_ENTITLEMENT = 'monthly_entitlement';

    public const TYPE_OVERTIME = 'overtime';

    public const TYPES = [
        self::TYPE_MONTHLY_ENTITLEMENT,
        self::TYPE_OVERTIME,
    ];

    protected $fillable = [
        'employee_id',
        'amount',
        'amount_input_mode',
        'amount_input_days',
        'amount_input_percent',
        'addition_date',
        'addition_type',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_input_days' => 'decimal:4',
        'amount_input_percent' => 'decimal:4',
        'addition_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
