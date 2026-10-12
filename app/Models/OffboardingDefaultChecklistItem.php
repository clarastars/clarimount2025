<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OffboardingDefaultChecklistItem extends Model
{
    protected $fillable = [
        'title',
        'sort_order',
        'attachment_mode',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(OffboardingDefaultItemApprovalStep::class, 'default_checklist_item_id');
    }
}
