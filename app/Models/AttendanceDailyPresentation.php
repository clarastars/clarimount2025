<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDailyPresentation extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'att_date',
        'status_ar',
        'late_minutes',
        'is_virtual_absence',
        'zk_daily_attendance_id',
        'first_punch',
        'last_punch',
        'punch_count',
        'first_verify_mode',
        'last_verify_mode',
        'device_pin',
        'device_name',
        'serial_number',
    ];

    protected $casts = [
        'att_date' => 'date',
        'late_minutes' => 'integer',
        'is_virtual_absence' => 'boolean',
        'first_punch' => 'datetime',
        'last_punch' => 'datetime',
        'punch_count' => 'integer',
        'first_verify_mode' => 'integer',
        'last_verify_mode' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
