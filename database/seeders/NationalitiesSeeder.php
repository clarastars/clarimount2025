<?php

namespace Database\Seeders;

use App\Models\Nationality;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NationalitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<array{code: string, name_en: string, name_ar: string}> $nationalities */
        $nationalities = require database_path('data/nationality_labels.php');

        foreach ($nationalities as $nationality) {
            Nationality::query()->updateOrCreate(
                ['code' => $nationality['code']],
                $nationality + ['is_active' => true],
            );
        }
    }
}
