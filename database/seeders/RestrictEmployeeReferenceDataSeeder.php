<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Nationality;
use Illuminate\Database\Seeder;

class RestrictEmployeeReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        Country::query()->update(['is_active' => false]);
        Nationality::query()->update(['is_active' => false]);

        $countries = [
            ['code' => 'SA', 'code_alpha3' => 'SAU', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية'],
            ['code' => 'EG', 'code_alpha3' => 'EGY', 'name_en' => 'Egypt', 'name_ar' => 'مصر'],
            ['code' => 'SY', 'code_alpha3' => 'SYR', 'name_en' => 'Syria', 'name_ar' => 'سوريا'],
            ['code' => 'PS', 'code_alpha3' => 'PSE', 'name_en' => 'Palestine', 'name_ar' => 'فلسطين'],
            ['code' => 'JO', 'code_alpha3' => 'JOR', 'name_en' => 'Jordan', 'name_ar' => 'الأردن'],
            ['code' => 'YE', 'code_alpha3' => 'YEM', 'name_en' => 'Yemen', 'name_ar' => 'اليمن'],
            ['code' => 'IN', 'code_alpha3' => 'IND', 'name_en' => 'India', 'name_ar' => 'الهند'],
            ['code' => 'BD', 'code_alpha3' => 'BGD', 'name_en' => 'Bangladesh', 'name_ar' => 'بنغلاديش'],
            ['code' => 'PK', 'code_alpha3' => 'PAK', 'name_en' => 'Pakistan', 'name_ar' => 'باكستان'],
            ['code' => 'SO', 'code_alpha3' => 'SOM', 'name_en' => 'Somalia', 'name_ar' => 'الصومال'],
            ['code' => 'SD', 'code_alpha3' => 'SDN', 'name_en' => 'Sudan', 'name_ar' => 'السودان'],
            ['code' => 'PH', 'code_alpha3' => 'PHL', 'name_en' => 'Philippines', 'name_ar' => 'الفلبين'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['code' => $country['code']],
                $country + ['is_active' => true],
            );
        }

        /** @var list<array{code: string, name_en: string, name_ar: string}> $allLabels */
        $allLabels = require database_path('data/nationality_labels.php');
        $allowedCodes = ['SAU', 'EGY', 'SYR', 'PSE', 'JOR', 'YEM', 'IND', 'BGD', 'PAK', 'SOM', 'SDN', 'PHL'];

        foreach ($allLabels as $nationality) {
            if (! in_array($nationality['code'], $allowedCodes, true)) {
                continue;
            }

            Nationality::query()->updateOrCreate(
                ['code' => $nationality['code']],
                $nationality + ['is_active' => true],
            );
        }
    }
}
