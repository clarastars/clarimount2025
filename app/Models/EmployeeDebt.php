<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDebt extends Model
{
    use HasFactory;

    public const TYPE_SALARY_CERTIFICATE_ATTESTATION = 'salary_certificate_attestation';

    public const TYPE_ADVANCE = 'advance';

    protected $fillable = [
        'employee_id',
        'amount',
        'original_amount',
        'monthly_installment',
        'debt_type',
        'salary_certificate_request_id',
        'advance_request_id',
        'pays_out_via_salary_run',
        'advance_disbursement_salary_run_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'pays_out_via_salary_run' => 'boolean',
    ];

    /**
     * Get the employee this debt belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryCertificateRequest(): BelongsTo
    {
        return $this->belongsTo(SalaryCertificateRequest::class);
    }

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(AdvanceRequest::class);
    }

    public function advanceDisbursementSalaryRun(): BelongsTo
    {
        return $this->belongsTo(SalaryRun::class, 'advance_disbursement_salary_run_id');
    }

    public function isAdvance(): bool
    {
        return $this->debt_type === self::TYPE_ADVANCE || $this->advance_request_id !== null;
    }

    public function paysOutViaSalaryRun(): bool
    {
        return (bool) $this->pays_out_via_salary_run;
    }

    /**
     * Total value of the debt when it was created; falls back to the remaining amount for legacy rows.
     */
    public function originalAmount(): float
    {
        return round((float) ($this->original_amount ?? $this->amount), 2);
    }

    public function paidAmount(): float
    {
        return round(max(0.0, $this->originalAmount() - (float) $this->amount), 2);
    }
}
