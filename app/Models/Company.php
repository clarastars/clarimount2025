<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'slug',
        'company_email',
        'fingerprint_report_name',
        'description_en',
        'description_ar',
        'logo',
        'website',
        'owner_id',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'name',
        'logo_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name_en);
            }
        });

        static::updating(function ($company) {
            if ($company->isDirty('name_en') && empty($company->slug)) {
                $company->slug = Str::slug($company->name_en);
            }
        });
    }

    /**
     * Get the company owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all asset categories for this company.
     */
    public function assetCategories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    /**
     * Get all locations for this company.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get all employees for this company.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all departments for this company.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }


    /**
     * Get all assets for this company.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the Bayzat configuration for this company.
     */
    public function bayzatConfig()
    {
        return $this->hasOne(BayzatConfig::class);
    }

    /**
     * Get all attendance import records for this company.
     */
    public function attendanceImportRecords(): HasMany
    {
        return $this->hasMany(AttendanceImportRecord::class);
    }

    /**
     * Get all salary runs for this company.
     */
    public function salaryRuns(): HasMany
    {
        return $this->hasMany(SalaryRun::class);
    }

    /**
     * Check if user is the owner of this company.
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Get company name based on locale.
     */
    public function getName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * Get localized name attribute for JSON serialization.
     */
    public function getNameAttribute(): string
    {
        return $this->getName();
    }

    /**
     * Get company description based on locale.
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        return $locale === 'ar' ? $this->description_ar : $this->description_en;
    }

    /**
     * Get public URL for company logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        return Storage::url($this->logo);
    }

    /**
     * Get company setting by key.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set company setting.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }
}
