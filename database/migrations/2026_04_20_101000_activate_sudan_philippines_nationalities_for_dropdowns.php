<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activate Sudan (SDN) and Philippines (PHL) for nationality/residence dropdowns (same pattern as Somalia migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->upsertNationalityFromLabels('SDN');
        $this->upsertNationalityFromLabels('PHL');
        $this->ensureCountryActive('SD', 'SDN', 'Sudan', 'السودان');
        $this->ensureCountryActive('PH', 'PHL', 'Philippines', 'الفلبين');
    }

    private function upsertNationalityFromLabels(string $alpha3Code): void
    {
        if (! Schema::hasTable('nationalities')) {
            return;
        }

        /** @var list<array{code: string, name_en: string, name_ar: string}> $labels */
        $labels = require database_path('data/nationality_labels.php');
        $row = collect($labels)->firstWhere('code', $alpha3Code);
        if ($row === null || ! isset($row['code'], $row['name_en'], $row['name_ar'])) {
            return;
        }

        $exists = DB::table('nationalities')->where('code', $alpha3Code)->exists();
        if ($exists) {
            DB::table('nationalities')->where('code', $alpha3Code)->update([
                'name_en' => $row['name_en'],
                'name_ar' => $row['name_ar'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('nationalities')->insert([
            'code' => $row['code'],
            'name_en' => $row['name_en'],
            'name_ar' => $row['name_ar'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  non-empty-string  $iso3166Alpha2
     * @param  non-empty-string  $iso3166Alpha3
     */
    private function ensureCountryActive(
        string $iso3166Alpha2,
        string $iso3166Alpha3,
        string $nameEn,
        string $nameAr,
    ): void {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $row = [
            'code' => $iso3166Alpha2,
            'code_alpha3' => $iso3166Alpha3,
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('countries')->where('code', $iso3166Alpha2)->exists()) {
            DB::table('countries')->where('code', $iso3166Alpha2)->update($row);

            return;
        }

        $row['created_at'] = now();
        DB::table('countries')->insert($row);
    }

    public function down(): void
    {
        //
    }
};
