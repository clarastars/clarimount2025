<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activate Afghanistan (AFG) nationality for employee dropdowns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nationalities')) {
            return;
        }

        /** @var list<array{code: string, name_en: string, name_ar: string}> $labels */
        $labels = require database_path('data/nationality_labels.php');
        $afg = collect($labels)->firstWhere('code', 'AFG');
        if ($afg === null || ! isset($afg['code'], $afg['name_en'], $afg['name_ar'])) {
            return;
        }

        $exists = DB::table('nationalities')->where('code', 'AFG')->exists();
        if ($exists) {
            DB::table('nationalities')->where('code', 'AFG')->update([
                'name_en' => $afg['name_en'],
                'name_ar' => $afg['name_ar'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('nationalities')->insert([
            'code' => $afg['code'],
            'name_en' => $afg['name_en'],
            'name_ar' => $afg['name_ar'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
