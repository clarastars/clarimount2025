<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'first_name',
        'father_name',
        'last_name',
        'nationality_id',
        'residence_country_id',
        'birth_date',
        'personal_email',
        'work_email',
        'personal_phone',
        'work_phone',
        'fingerprint_device_id',
        'shift_id',
        'work_address',
        'department',
        'department_id',
        'job_title',
        'basic_salary',
        'allowances',
        'allowance_housing',
        'allowance_transportation',
        'allowance_other',
        'allowance_food',
        'allowance_personal_car',
        'social_insurance_deduction_rate',
        'annual_leave_balance',
        'leave_accrued_balance',
        'leave_days_used',
        'manager',
        'direct_manager',
        'additional_approver_2',
        'additional_approver_3',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'departure_date',
        'departure_reason',
        'employment_status',
        'id_number',
        'residence_expiry_date',
        'contract_end_date',
        'exit_reentry_visa_expiry',
        'passport_number',
        'passport_expiry_date',
        'insurance_policy',
        'insurance_expiry_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_email',
        'emergency_contact_address',
        'company_id',
        'user_id',
        'notes',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'departure_date' => 'date',
        'birth_date' => 'date',
        'residence_expiry_date' => 'date',
        'contract_end_date' => 'date',
        'exit_reentry_visa_expiry' => 'date',
        'passport_expiry_date' => 'date',
        'insurance_expiry_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'allowance_housing' => 'decimal:2',
        'allowance_transportation' => 'decimal:2',
        'allowance_other' => 'decimal:2',
        'allowance_food' => 'decimal:2',
        'allowance_personal_car' => 'decimal:2',
        'social_insurance_deduction_rate' => 'decimal:2',
        'annual_leave_balance' => 'integer',
        'leave_accrued_balance' => 'decimal:2',
        'leave_days_used' => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
        'display_name',
    ];

    /**
     * Get all leaves for this employee.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the company this employee belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the nationality of this employee.
     */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Nationality::class);
    }

    /**
     * Get the residence country of this employee.
     */
    public function residenceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'residence_country_id');
    }

    /**
     * Get all assets assigned to this employee.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_to');
    }

    /**
     * Get all asset assignments for this employee.
     */
    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Get all tickets reported by this employee.
     */
    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    /**
     * Get all salary run items for this employee.
     */
    public function salaryRunItems(): HasMany
    {
        return $this->hasMany(SalaryRunItem::class);
    }

    /**
     * Get all debts for this employee.
     */
    public function debts(): HasMany
    {
        return $this->hasMany(EmployeeDebt::class);
    }

    /**
     * Get the shift assigned to this employee
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get the portal user linked to this employee (for employee self-service login).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employee's full name.
     */
    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->father_name, $this->last_name])
            ->filter(static fn (?string $part): bool => filled($part))
            ->implode(' ');
    }

    /**
     * Get the employee's display name (ID + Name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->employee_id . ' - ' . $this->full_name;
    }

    /**
     * Get remaining annual leave balance (accrued minus pre-system used days minus approved leave deductions).
     */
    public function getRemainingAnnualLeaveBalanceAttribute(): float
    {
        $accrued = (float) ($this->attributes['leave_accrued_balance'] ?? 0);
        $previouslyUsed = (float) ($this->attributes['leave_days_used'] ?? 0);
        $deducted = (int) $this->leaves()
            ->where('deduct_from_balance', true)
            ->sum('days');

        return max(0, round($accrued - $previouslyUsed - $deducted, 2));
    }

    public function monthlyLeaveAccrualDays(): float
    {
        $entitlement = (int) ($this->annual_leave_balance ?? 0);

        if ($entitlement <= 0) {
            return 0.0;
        }

        return round($entitlement / 12, 2);
    }

    /**
     * Scope for active employees only.
     */
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }

    /**
     * Check if employee is active.
     */
    public function isActive(): bool
    {
        return $this->employment_status === 'active';
    }
}
