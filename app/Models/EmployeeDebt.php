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

    protected $fillable = [
        'employee_id',
        'amount',
        'debt_type',
        'salary_certificate_request_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
}
