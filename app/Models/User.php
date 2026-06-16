<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uses_password_login',
        'language',
        'team_id',
        'joined_team_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'joined_team_at' => 'datetime',
            'password' => 'hashed',
            'uses_password_login' => 'boolean',
        ];
    }

    /**
     * Get the team that the user belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all teams owned by this user.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Get all companies owned by this user.
     */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * Companies this user can access through assigned role scope.
     */
    public function accessibleCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user_access')
            ->withTimestamps();
    }

    /**
     * Get the current company for the user.
     * This is a simplified approach - in a real app you might want to store 
     * the current company ID in session or user preferences.
     */
    public function currentCompany(): ?Company
    {
        // For now, return the first company the user owns
        // In a more complex system, you'd implement company switching
        return $this->ownedCompanies()->first();
    }

    /**
     * Check if user owns the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->id === $team->owner_id;
    }

    /**
     * Check if user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->team_id === $team->id;
    }

    /**
     * Check if user has permission in their current team context.
     */
    public function hasTeamPermission(string $permission): bool
    {
        if (!$this->team_id) {
            return false;
        }

        return $this->hasPermissionTo($permission, 'web', $this->team_id);
    }

    /**
     * Check if user has role in their current team context.
     */
    public function hasTeamRole(string $role): bool
    {
        if (!$this->team_id) {
            return false;
        }

        return $this->hasRole($role . '-' . $this->team_id, 'web');
    }

    /**
     * Assign role to user in their current team context.
     */
    public function assignTeamRole(string $role): void
    {
        if ($this->team_id) {
            $this->assignRole($role . '-' . $this->team_id, 'web');
        }
    }

    /**
     * Give permission to user in their current team context.
     */
    public function giveTeamPermission(string $permission): void
    {
        if ($this->team_id) {
            $this->givePermissionTo($permission, 'web', $this->team_id);
        }
    }

    /**
     * Get user's roles for their current team.
     */
    public function getTeamRoles()
    {
        if (!$this->team_id) {
            return collect();
        }

        return $this->roles()->where('team_id', $this->team_id)->get();
    }

    /**
     * Get user's team role name (without team suffix)
     */
    public function getTeamRoleName(): ?string
    {
        $teamRole = $this->getTeamRoles()->first();
        if (!$teamRole) {
            return null;
        }
        
        // Remove the team ID suffix from role name
        return str_replace('-' . $this->team_id, '', $teamRole->name);
    }

    /**
     * Get user's permissions for their current team.
     */
    public function getTeamPermissions()
    {
        if (!$this->team_id) {
            return collect();
        }

        return $this->getAllPermissions($this->team_id);
    }

    /**
     * Switch user to a different team.
     */
    public function switchToTeam(Team $team): void
    {
        if ($team->hasMember($this) || $team->isOwner($this)) {
            $this->update([
                'team_id' => $team->id,
                'joined_team_at' => now(),
            ]);
        }
    }

    /**
     * Get the team ID for Spatie Permission context.
     */
    public function getPermissionTeamId()
    {
        return $this->team_id;
    }

    /**
     * Get the employee record linked to this user (when user has employee role).
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
