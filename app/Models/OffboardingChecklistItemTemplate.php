<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OffboardingChecklistItemTemplate extends Model
{
    public const ATTACHMENT_NONE = 'none';

    public const ATTACHMENT_OPTIONAL = 'optional';

    public const ATTACHMENT_REQUIRED = 'required';

    protected $fillable = [
        'company_id',
        'title',
        'sort_order',
        'attachment_mode',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(OffboardingItemApprovalStep::class, 'template_id');
    }

    public function instanceItems(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingItem::class, 'template_id');
    }
}
