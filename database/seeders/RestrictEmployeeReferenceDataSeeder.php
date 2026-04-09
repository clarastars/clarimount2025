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
            ['code' => 'BD', 'code_alpha3' => 'BGD', 'name_en' => 'Bangladesh', 'name_ar' => 'بنجلاديش'],
            ['code' => 'PK', 'code_alpha3' => 'PAK', 'name_en' => 'Pakistan', 'name_ar' => 'باكستان'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['code' => $country['code']],
                $country + ['is_active' => true],
            );
        }

        $nationalities = [
            ['code' => 'SAU', 'name_en' => 'Saudi', 'name_ar' => 'سعودي'],
            ['code' => 'EGY', 'name_en' => 'Egyptian', 'name_ar' => 'مصري'],
            ['code' => 'SYR', 'name_en' => 'Syrian', 'name_ar' => 'سوري'],
            ['code' => 'PSE', 'name_en' => 'Palestinian', 'name_ar' => 'فلسطيني'],
            ['code' => 'JOR', 'name_en' => 'Jordanian', 'name_ar' => 'أردني'],
            ['code' => 'YEM', 'name_en' => 'Yemeni', 'name_ar' => 'يمني'],
            ['code' => 'IND', 'name_en' => 'Indian', 'name_ar' => 'هندي'],
            ['code' => 'BGD', 'name_en' => 'Bangladeshi', 'name_ar' => 'بنغلاديشي'],
            ['code' => 'PAK', 'name_en' => 'Pakistani', 'name_ar' => 'باكستاني'],
        ];

        foreach ($nationalities as $nationality) {
            Nationality::query()->updateOrCreate(
                ['code' => $nationality['code']],
                $nationality + ['is_active' => true],
            );
        }
    }
}

