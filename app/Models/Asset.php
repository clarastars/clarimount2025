<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Utf8;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class Asset extends Model implements Searchable
{
    public $searchableType = 'Asset';
    
    use HasFactory, HasUuids;

    protected $fillable = [
        'asset_tag',
        'serial_number',
        'service_tag_number',
        'finance_tag_number',
        'asset_category_id',
        'asset_template_id',
        'location_id',
        'company_id',
        'assigned_to',
        'assigned_date',
        'model_name',
        'model_number',
        'manufacturer',
        'status',
        'condition',
        'notes',
        'image_path',
    ];

    protected $casts = [
        'status' => 'string',
        'assigned_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($asset) {
            if (empty($asset->asset_tag)) {
                $asset->asset_tag = static::generateUniqueAssetTag($asset->company_id);
            }
        });
    }

    /**
     * Generate a unique sequential asset tag for the company.
     * Format: [Company Name Abbreviation]-[3-digit sequential number]
     * Example: "Advanced Line for Technology" -> ADTY-001, ADTY-002, ADTY-003...
     * Example: "Global Tech Solutions" -> GTSL-001, GTSL-002, GTSL-003...
     */
    public static function generateUniqueAssetTag($companyId): string
    {
        // Get the company to extract name
        $company = Company::find($companyId);
        if (!$company) {
            // Fallback to company ID if company not found
            $companyPrefix = str_pad($companyId % 100, 2, '0', STR_PAD_LEFT);
        } else {
            // Generate company abbreviation from name
            $companyPrefix = static::generateCompanyAbbreviation($company->name_en ?? '');
        }

        $companyPrefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $companyPrefix) ?: 'ASST');
        $companyPrefix = substr($companyPrefix, 0, 4) ?: 'ASST';
        
        // Pattern for this company's asset tags (e.g., "ADTY-" for Advanced Line for Technology)
        $pattern = $companyPrefix . '-';
        
        // Get the highest asset tag number for this company with this pattern
        $lastAsset = static::where('company_id', $companyId)
            ->where('asset_tag', 'like', $pattern . '%')
            ->whereRaw('asset_tag REGEXP ?', ["^{$companyPrefix}-[0-9]+$"])
            ->orderByRaw('LENGTH(asset_tag) DESC, asset_tag DESC')
            ->first();
        
        if ($lastAsset) {
            // Extract the number part after the dash and increment
            $lastNumber = intval(substr($lastAsset->asset_tag, strlen($companyPrefix) + 1));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1; // Start from 1 for each company
        }
        
        // Format as 3-digit number with leading zeros (001, 002, etc.)
        $formattedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        $assetTag = $pattern . $formattedNumber;
        
        // Ensure uniqueness (just in case)
        while (static::where('company_id', $companyId)->where('asset_tag', $assetTag)->exists()) {
            $nextNumber++;
            $formattedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $assetTag = $pattern . $formattedNumber;
        }
        
        return $assetTag;
    }

    protected static function normalizeAssetTagPrefix(string $prefix): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', Utf8::sanitize($prefix) ?? '') ?: '');

        return substr($normalized, 0, 4) ?: 'ASST';
    }

    /**
     * Generate a 4-letter abbreviation from company name with collision detection.
     * Examples:
     * "Advanced Line for Chemicals" -> "ADLC" (2+1+1)
     * "Advanced Line Information Technology" -> "ALIT" (1+1+1+1)
     * "Global Tech Solutions" -> "GTSL" (2+2)
     * "Microsoft Corporation" -> "MSCR" (4 letters from single word)
     * "ABC Company" -> "ABCX" (3 letters + padding)
     */
    protected static function generateCompanyAbbreviation(string $companyName): string
    {
        $companyName = Utf8::sanitize($companyName) ?? '';

        // Remove common business words and clean the name
        $commonWords = ['corporation', 'corp', 'company', 'co', 'inc', 'incorporated', 'ltd', 'limited', 'llc', 'for', 'and', 'the', 'of', 'in', 'to'];
        $cleanName = str_replace($commonWords, '', strtolower($companyName));
        
        // Split into words and remove empty elements, then re-index
        $words = array_values(array_filter(explode(' ', trim($cleanName))));
        
        // Try different abbreviation strategies for better uniqueness
        $abbreviations = [];
        
        if (count($words) >= 4) {
            // Strategy 1: First letter from first 4 words (e.g., "Advanced Line Information Technology" -> "ALIT")
            $abbreviations[] = substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1) . substr($words[3], 0, 1);
            // Strategy 2: 2 letters from first 2 words as fallback
            $abbreviations[] = substr($words[0], 0, 2) . substr($words[1], 0, 2);
        } elseif (count($words) == 3) {
            // Strategy 1: 2 letters from first word, 1 from second and third (e.g., "Advanced Line Chemicals" -> "ADLC")
            $abbreviations[] = substr($words[0], 0, 2) . substr($words[1], 0, 1) . substr($words[2], 0, 1);
            // Strategy 2: 1 letter from each + 1 more from first
            $abbreviations[] = substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 2);
        } elseif (count($words) == 2) {
            // Strategy 1: 2 letters from each word
            $abbreviations[] = substr($words[0], 0, 2) . substr($words[1], 0, 2);
            // Strategy 2: 3 letters from first word + 1 from second
            $abbreviations[] = substr($words[0], 0, 3) . substr($words[1], 0, 1);
            // Strategy 3: 1 letter from first + 3 from second
            $abbreviations[] = substr($words[0], 0, 1) . substr($words[1], 0, 3);
        } elseif (count($words) == 1) {
            // Take first 4 letters if only one word
            $abbreviations[] = substr($words[0], 0, 4);
        } else {
            // Fallback: take first 4 letters of original name (alphanumeric only)
            $alphanumeric = preg_replace('/[^a-zA-Z0-9]/', '', $companyName);
            $abbreviations[] = substr($alphanumeric, 0, 4);
        }
        
        // Check for existing abbreviations in the database to avoid collisions
        $existingAbbreviations = \DB::table('assets')
            ->join('companies', 'assets.company_id', '=', 'companies.id')
            ->where('companies.name_en', '!=', $companyName)
            ->pluck('asset_tag')
            ->map(function ($tag) {
                return explode('-', $tag)[0]; // Extract abbreviation part before the dash
            })
            ->unique()
            ->filter() // Remove empty values
            ->toArray();
        
        // Find the first abbreviation that doesn't collide
        foreach ($abbreviations as $abbreviation) {
            $abbreviation = static::normalizeAssetTagPrefix($abbreviation);

            if (!in_array($abbreviation, $existingAbbreviations)) {
                return $abbreviation;
            }
        }

        // If all strategies fail, add a numeric suffix to make it unique
        $baseAbbr = substr(static::normalizeAssetTagPrefix($abbreviations[0] ?? 'ASST'), 0, 3);
        $counter = 1;
        do {
            $abbreviation = $baseAbbr.$counter;
            $counter++;
        } while (in_array($abbreviation, $existingAbbreviations) && $counter < 10);

        return static::normalizeAssetTagPrefix($abbreviation);
    }

    /**
     * Get the template this asset was created from.
     */
    public function assetTemplate(): BelongsTo
    {
        return $this->belongsTo(AssetTemplate::class);
    }

    /**
     * Get the category this asset belongs to.
     */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * Alias for assetCategory relationship (for consistency with frontend)
     */
    public function category(): BelongsTo
    {
        return $this->assetCategory();
    }

    /**
     * Get the location this asset is at.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the company this asset belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the employee this asset is assigned to.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * Get all assignment history for this asset.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Get the asset's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->asset_tag];
        
        if ($this->model_name) {
            $parts[] = $this->model_name;
        }
        
        if ($this->model_number) {
            $parts[] = "({$this->model_number})";
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Get the current active assignment.
     */
    public function currentAssignment()
    {
        return $this->assignments()->where('status', 'active')->first();
    }

    /**
     * Check if asset is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->status === 'assigned' || $this->assignments()->where('status', 'active')->exists();
    }

    /**
     * Scope for assets by company.
     */
    public function scopeCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for assets by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get all available status values.
     */
    public static function getStatuses(): array
    {
        return ['available', 'assigned', 'maintenance', 'retired'];
    }

    /**
     * Get the search result for this model.
     */
    public function getSearchResult(): SearchResult
    {
        $url = route('assets.show', $this->id);
        
        return new SearchResult(
            $this,
            $this->asset_tag,
            $url
        );
    }
}
