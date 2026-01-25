<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDebt extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'debt_type',
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
}
