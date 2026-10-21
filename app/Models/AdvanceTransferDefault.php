<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceTransferDefault extends Model
{
    protected $fillable = [
        'team_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();

        if ($row !== null) {
            return $row;
        }

        return static::query()->create(['team_id' => null]);
    }
}
