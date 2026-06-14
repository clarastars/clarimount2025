<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZkDailyAttendance extends Model
{
    use HasFactory;

    protected $table = 'zk_daily_attendance';

    protected $fillable = [
        'device_id',
        'device_pin',
        'att_date',
        'first_punch',
        'last_punch',
        'first_verify_mode',
        'last_verify_mode',
        'punch_count',
    ];

    protected $casts = [
        'att_date' => 'date',
        'first_punch' => 'datetime',
        'last_punch' => 'datetime',
        'first_verify_mode' => 'integer',
        'last_verify_mode' => 'integer',
        'punch_count' => 'integer',
    ];

    /**
     * Get the device that owns this daily attendance record
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(ZkDevice::class, 'device_id');
    }

    /**
     * Get the employee associated with this attendance record via device_pin
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'device_pin', 'fingerprint_device_id');
    }

    /**
     * One punch = check-in only; check-out is set only when there are 2+ punches.
     */
    public function syncCheckoutWithPunchCount(): void
    {
        if (($this->punch_count ?? 0) <= 1) {
            $this->last_punch = null;
            $this->last_verify_mode = null;
        }
    }
}
