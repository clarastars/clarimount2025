<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePenalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'late_minutes',
        'violation_type',
        'repeat_number',
        'action_type',
        'action_value',
        'action_value_gross_days',
        'action_value_basic_days',
        'action_text',
        'reason_text',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejection_attachment_path',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'late_minutes' => 'integer',
        'repeat_number' => 'integer',
        'action_value' => 'integer',
        'action_value_gross_days' => 'integer',
        'action_value_basic_days' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the employee that this penalty belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to filter by employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by year (calendar year)
     */
    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('attendance_date', $year);
    }

    /**
     * Scope to filter by violation type
     */
    public function scopeByViolationType($query, string $violationType)
    {
        return $query->where('violation_type', $violationType);
    }

    /**
     * Scope to filter by approval status
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope to filter by pending approval
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Get the user who approved/rejected this penalty
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if penalty is approved
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Check if penalty is rejected
     */
    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    /**
     * Check if penalty is pending
     */
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }
}
