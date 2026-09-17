<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveDaysUsedImportRow extends Model
{
    protected $fillable = [
        'leave_days_used_import_id',
        'excel_row',
        'id_number',
        'employee_id',
        'days_added',
        'previous_value',
        'new_value',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'excel_row' => 'integer',
            'days_added' => 'decimal:2',
            'previous_value' => 'decimal:2',
            'new_value' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LeaveDaysUsedImport::class, 'leave_days_used_import_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
