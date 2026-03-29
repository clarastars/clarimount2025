<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftWorkday extends Model
{
    use HasFactory;

    protected $table = 'shift_workdays';

    protected $fillable = [
        'shift_id',
        'weekday',
        'is_workday',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'is_workday' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the shift that owns this workday
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
