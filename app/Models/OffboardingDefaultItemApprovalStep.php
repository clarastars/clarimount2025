<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingDefaultItemApprovalStep extends Model
{
    protected $fillable = [
        'default_checklist_item_id',
        'title',
        'sort_order',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(OffboardingDefaultChecklistItem::class, 'default_checklist_item_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
