<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveDaysUsedImport extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'updated_count',
        'skipped_count',
        'rows_processed',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'rows_processed' => 'integer',
            'undone_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(LeaveDaysUsedImportRow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    public function canUndo(): bool
    {
        return ! $this->isUndone() && $this->updated_count > 0;
    }
}
