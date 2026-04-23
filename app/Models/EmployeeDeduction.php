<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeduction extends Model
{
    public const TYPE_PENALTIES = 'penalties';
    public const TYPE_ABSENCE = 'absence';
    public const TYPE_TRAFFIC_VIOLATION = 'traffic_violation';
    public const TYPE_ATTESTATIONS = 'attestations';

    public const TYPES = [
        self::TYPE_PENALTIES,
        self::TYPE_ABSENCE,
        self::TYPE_TRAFFIC_VIOLATION,
        self::TYPE_ATTESTATIONS,
    ];

    protected $fillable = [
        'employee_id',
        'amount',
        'deduction_date',
        'deduction_type',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deduction_date' => 'date',
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
