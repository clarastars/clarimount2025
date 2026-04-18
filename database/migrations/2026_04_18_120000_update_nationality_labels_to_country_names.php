<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align nationality dropdown labels with country names (not demonyms), e.g. Egypt/مصر instead of Egyptian/مصري.
     */
    public function up(): void
    {
        /** @var list<array{code: string, name_en: string, name_ar: string}> $labels */
        $labels = require database_path('data/nationality_labels.php');

        foreach ($labels as $row) {
            DB::table('nationalities')->where('code', $row['code'])->update([
                'name_en' => $row['name_en'],
                'name_ar' => $row['name_ar'],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
