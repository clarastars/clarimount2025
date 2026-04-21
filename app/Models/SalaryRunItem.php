<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_run_id',
        'employee_id',
        'basic_salary',
        'allowances',
        'gross_salary',
        'penalties_total',
        'social_insurance_deduction_total',
        'unpaid_leave_total',
        'net_salary',
        'breakdown',
        'breakdown_exclusions',
        'debt_deductions',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'penalties_total' => 'decimal:2',
        'social_insurance_deduction_total' => 'decimal:2',
        'unpaid_leave_total' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'breakdown' => 'array',
        'breakdown_exclusions' => 'array',
        'debt_deductions' => 'array',
    ];

    /**
     * Get the salary run this item belongs to
     */
    public function salaryRun(): BelongsTo
    {
        return $this->belongsTo(SalaryRun::class);
    }

    /**
     * Get the employee this item belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
