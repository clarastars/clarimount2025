<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'settings',
        'is_active',
        'owner_id',
        'trial_ends_at',
        'subscription_status',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($team) {
            if (empty($team->slug)) {
                $team->slug = Str::slug($team->name);
            }
        });

        static::updating(function ($team) {
            if ($team->isDirty('name') && empty($team->slug)) {
                $team->slug = Str::slug($team->name);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users in this team.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }


    /**
     * Get active users in this team.
     */
    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class)->whereNotNull('email_verified_at');
    }

    /**
     * Check if the team is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the team subscription is active.
     */
    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, ['active', 'trial']) && 
               ($this->subscription_status !== 'trial' || $this->isOnTrial());
    }

    /**
     * Check if user is the owner of this team.
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if user is a member of this team.
     */
    public function hasMember(User $user): bool
    {
        return $this->users()->where('id', $user->id)->exists();
    }

    /**
     * Get team setting by key.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set team setting.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }
}
