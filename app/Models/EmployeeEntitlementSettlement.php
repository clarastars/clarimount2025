<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEntitlementSettlement extends Model
{
    protected $fillable = [
        'employee_id',
        'created_by',
        'settlement_date',
        'reason',
        'last_settlement_date',
        'service_days',
        'basic_salary',
        'allowances',
        'gross_salary',
        'remaining_leave_days',
        'salary_unpaid_days',
        'used_annual_leave_days',
        'end_of_service_bonus',
        'travel_tickets',
        'due_commissions',
        'salary_dues',
        'annual_leave_dues',
        'other_dues',
        'total_dues',
        'advances_deduction',
        'custody_deduction',
        'excess_leave_deduction',
        'social_insurance_deduction',
        'used_annual_leave_deduction',
        'total_deductions',
        'net_due',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'last_settlement_date' => 'date',
        'service_days' => 'integer',
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'remaining_leave_days' => 'decimal:2',
        'salary_unpaid_days' => 'decimal:2',
        'used_annual_leave_days' => 'decimal:2',
        'end_of_service_bonus' => 'decimal:2',
        'travel_tickets' => 'decimal:2',
        'due_commissions' => 'decimal:2',
        'salary_dues' => 'decimal:2',
        'annual_leave_dues' => 'decimal:2',
        'other_dues' => 'decimal:2',
        'total_dues' => 'decimal:2',
        'advances_deduction' => 'decimal:2',
        'custody_deduction' => 'decimal:2',
        'excess_leave_deduction' => 'decimal:2',
        'social_insurance_deduction' => 'decimal:2',
        'used_annual_leave_deduction' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_due' => 'decimal:2',
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
