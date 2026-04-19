<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Somalia (SOM) was omitted from RestrictEmployeeReferenceDataSeeder allowed codes — activate for nationality/residence dropdowns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->upsertSomaliaNationality();
        $this->ensureSomaliaCountryActive();
    }

    private function upsertSomaliaNationality(): void
    {
        if (! Schema::hasTable('nationalities')) {
            return;
        }

        /** @var list<array{code: string, name_en: string, name_ar: string}> $labels */
        $labels = require database_path('data/nationality_labels.php');
        $som = collect($labels)->firstWhere('code', 'SOM');
        if ($som === null || ! isset($som['code'], $som['name_en'], $som['name_ar'])) {
            return;
        }

        $exists = DB::table('nationalities')->where('code', 'SOM')->exists();
        if ($exists) {
            DB::table('nationalities')->where('code', 'SOM')->update([
                'name_en' => $som['name_en'],
                'name_ar' => $som['name_ar'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('nationalities')->insert([
            'code' => $som['code'],
            'name_en' => $som['name_en'],
            'name_ar' => $som['name_ar'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureSomaliaCountryActive(): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $row = [
            'code' => 'SO',
            'code_alpha3' => 'SOM',
            'name_en' => 'Somalia',
            'name_ar' => 'الصومال',
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('countries')->where('code', 'SO')->exists()) {
            DB::table('countries')->where('code', 'SO')->update($row);

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
