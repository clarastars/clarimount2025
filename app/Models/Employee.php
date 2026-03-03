<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'first_name',
        'father_name',
        'last_name',
        'nationality_id',
        'residence_country_id',
        'birth_date',
        'email',
        'personal_email',
        'work_email',
        'phone',
        'work_phone',
        'mobile',
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
        'annual_leave_balance',
        'manager',
        'direct_manager',
        'additional_approver_2',
        'additional_approver_3',
        'hire_date',
        'employment_date',
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
        'notes',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'hire_date' => 'date',
        'employment_date' => 'date',
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
        'annual_leave_balance' => 'integer',
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
     * Get the employee's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the employee's display name (ID + Name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->employee_id . ' - ' . $this->full_name;
    }

    /**
     * Get remaining annual leave balance (annual_leave_balance minus deducted days).
     */
    public function getRemainingAnnualLeaveBalanceAttribute(): int
    {
        $balance = (int) ($this->attributes['annual_leave_balance'] ?? 0);
        $deducted = (int) $this->leaves()
            ->where('deduct_from_balance', true)
            ->sum('days');

        return max(0, $balance - $deducted);
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
