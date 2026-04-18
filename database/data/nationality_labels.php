<?php

declare(strict_types=1);

/**
 * Nationality dropdown labels as country names (not demonyms), for add/edit employee forms.
 * Keys: code (ISO 3166-1 alpha-3 style), name_en, name_ar.
 *
 * @return list<array{code: string, name_en: string, name_ar: string}>
 */
return [
    // Middle East & Gulf
    ['code' => 'SAU', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية'],
    ['code' => 'UAE', 'name_en' => 'United Arab Emirates', 'name_ar' => 'الإمارات العربية المتحدة'],
    ['code' => 'KWT', 'name_en' => 'Kuwait', 'name_ar' => 'الكويت'],
    ['code' => 'QAT', 'name_en' => 'Qatar', 'name_ar' => 'قطر'],
    ['code' => 'BHR', 'name_en' => 'Bahrain', 'name_ar' => 'البحرين'],
    ['code' => 'OMN', 'name_en' => 'Oman', 'name_ar' => 'عُمان'],
    ['code' => 'JOR', 'name_en' => 'Jordan', 'name_ar' => 'الأردن'],
    ['code' => 'LBN', 'name_en' => 'Lebanon', 'name_ar' => 'لبنان'],
    ['code' => 'SYR', 'name_en' => 'Syria', 'name_ar' => 'سوريا'],
    ['code' => 'IRQ', 'name_en' => 'Iraq', 'name_ar' => 'العراق'],
    ['code' => 'IRN', 'name_en' => 'Iran', 'name_ar' => 'إيران'],
    ['code' => 'TUR', 'name_en' => 'Turkey', 'name_ar' => 'تركيا'],
    ['code' => 'ISR', 'name_en' => 'Israel', 'name_ar' => 'إسرائيل'],
    ['code' => 'PSE', 'name_en' => 'Palestine', 'name_ar' => 'فلسطين'],
    ['code' => 'YEM', 'name_en' => 'Yemen', 'name_ar' => 'اليمن'],

    // North Africa
    ['code' => 'EGY', 'name_en' => 'Egypt', 'name_ar' => 'مصر'],
    ['code' => 'LBY', 'name_en' => 'Libya', 'name_ar' => 'ليبيا'],
    ['code' => 'TUN', 'name_en' => 'Tunisia', 'name_ar' => 'تونس'],
    ['code' => 'DZA', 'name_en' => 'Algeria', 'name_ar' => 'الجزائر'],
    ['code' => 'MAR', 'name_en' => 'Morocco', 'name_ar' => 'المغرب'],
    ['code' => 'SDN', 'name_en' => 'Sudan', 'name_ar' => 'السودان'],

    // Major international
    ['code' => 'USA', 'name_en' => 'United States', 'name_ar' => 'الولايات المتحدة'],
    ['code' => 'GBR', 'name_en' => 'United Kingdom', 'name_ar' => 'المملكة المتحدة'],
    ['code' => 'CAN', 'name_en' => 'Canada', 'name_ar' => 'كندا'],
    ['code' => 'AUS', 'name_en' => 'Australia', 'name_ar' => 'أستراليا'],
    ['code' => 'NZL', 'name_en' => 'New Zealand', 'name_ar' => 'نيوزيلندا'],
    ['code' => 'DEU', 'name_en' => 'Germany', 'name_ar' => 'ألمانيا'],
    ['code' => 'FRA', 'name_en' => 'France', 'name_ar' => 'فرنسا'],
    ['code' => 'ITA', 'name_en' => 'Italy', 'name_ar' => 'إيطاليا'],
    ['code' => 'ESP', 'name_en' => 'Spain', 'name_ar' => 'إسبانيا'],
    ['code' => 'PRT', 'name_en' => 'Portugal', 'name_ar' => 'البرتغال'],
    ['code' => 'NLD', 'name_en' => 'Netherlands', 'name_ar' => 'هولندا'],
    ['code' => 'BEL', 'name_en' => 'Belgium', 'name_ar' => 'بلجيكا'],
    ['code' => 'CHE', 'name_en' => 'Switzerland', 'name_ar' => 'سويسرا'],
    ['code' => 'AUT', 'name_en' => 'Austria', 'name_ar' => 'النمسا'],
    ['code' => 'SWE', 'name_en' => 'Sweden', 'name_ar' => 'السويد'],
    ['code' => 'NOR', 'name_en' => 'Norway', 'name_ar' => 'النرويج'],
    ['code' => 'DNK', 'name_en' => 'Denmark', 'name_ar' => 'الدنمارك'],
    ['code' => 'FIN', 'name_en' => 'Finland', 'name_ar' => 'فنلندا'],
    ['code' => 'RUS', 'name_en' => 'Russia', 'name_ar' => 'روسيا'],
    ['code' => 'POL', 'name_en' => 'Poland', 'name_ar' => 'بولندا'],
    ['code' => 'GRC', 'name_en' => 'Greece', 'name_ar' => 'اليونان'],

    // Asia
    ['code' => 'CHN', 'name_en' => 'China', 'name_ar' => 'الصين'],
    ['code' => 'JPN', 'name_en' => 'Japan', 'name_ar' => 'اليابان'],
    ['code' => 'KOR', 'name_en' => 'South Korea', 'name_ar' => 'كوريا الجنوبية'],
    ['code' => 'IND', 'name_en' => 'India', 'name_ar' => 'الهند'],
    ['code' => 'PAK', 'name_en' => 'Pakistan', 'name_ar' => 'باكستان'],
    ['code' => 'BGD', 'name_en' => 'Bangladesh', 'name_ar' => 'بنغلاديش'],
    ['code' => 'LKA', 'name_en' => 'Sri Lanka', 'name_ar' => 'سريلانكا'],
    ['code' => 'THA', 'name_en' => 'Thailand', 'name_ar' => 'تايلاند'],
    ['code' => 'MYS', 'name_en' => 'Malaysia', 'name_ar' => 'ماليزيا'],
    ['code' => 'SGP', 'name_en' => 'Singapore', 'name_ar' => 'سنغافورة'],
    ['code' => 'IDN', 'name_en' => 'Indonesia', 'name_ar' => 'إندونيسيا'],
    ['code' => 'PHL', 'name_en' => 'Philippines', 'name_ar' => 'الفلبين'],
    ['code' => 'VNM', 'name_en' => 'Vietnam', 'name_ar' => 'فيتنام'],
    ['code' => 'KAZ', 'name_en' => 'Kazakhstan', 'name_ar' => 'كازاخستان'],
    ['code' => 'UZB', 'name_en' => 'Uzbekistan', 'name_ar' => 'أوزبكستان'],
    ['code' => 'AFG', 'name_en' => 'Afghanistan', 'name_ar' => 'أفغانستان'],

    // Africa
    ['code' => 'ZAF', 'name_en' => 'South Africa', 'name_ar' => 'جنوب أفريقيا'],
    ['code' => 'NGA', 'name_en' => 'Nigeria', 'name_ar' => 'نيجيريا'],
    ['code' => 'KEN', 'name_en' => 'Kenya', 'name_ar' => 'كينيا'],
    ['code' => 'ETH', 'name_en' => 'Ethiopia', 'name_ar' => 'إثيوبيا'],
    ['code' => 'GHA', 'name_en' => 'Ghana', 'name_ar' => 'غانا'],
    ['code' => 'UGA', 'name_en' => 'Uganda', 'name_ar' => 'أوغندا'],
    ['code' => 'TZA', 'name_en' => 'Tanzania', 'name_ar' => 'تنزانيا'],
    ['code' => 'SOM', 'name_en' => 'Somalia', 'name_ar' => 'الصومال'],
    ['code' => 'DJI', 'name_en' => 'Djibouti', 'name_ar' => 'جيبوتي'],
    ['code' => 'ERI', 'name_en' => 'Eritrea', 'name_ar' => 'إريتريا'],

    // South America
    ['code' => 'BRA', 'name_en' => 'Brazil', 'name_ar' => 'البرازيل'],
    ['code' => 'ARG', 'name_en' => 'Argentina', 'name_ar' => 'الأرجنتين'],
    ['code' => 'CHL', 'name_en' => 'Chile', 'name_ar' => 'تشيلي'],
    ['code' => 'COL', 'name_en' => 'Colombia', 'name_ar' => 'كولومبيا'],
    ['code' => 'PER', 'name_en' => 'Peru', 'name_ar' => 'بيرو'],
    ['code' => 'VEN', 'name_en' => 'Venezuela', 'name_ar' => 'فنزويلا'],
    ['code' => 'URY', 'name_en' => 'Uruguay', 'name_ar' => 'أوروغواي'],
    ['code' => 'PRY', 'name_en' => 'Paraguay', 'name_ar' => 'باراغواي'],
    ['code' => 'BOL', 'name_en' => 'Bolivia', 'name_ar' => 'بوليفيا'],
    ['code' => 'ECU', 'name_en' => 'Ecuador', 'name_ar' => 'الإكوادور'],

    // Additional
    ['code' => 'MEX', 'name_en' => 'Mexico', 'name_ar' => 'المكسيك'],
    ['code' => 'CUB', 'name_en' => 'Cuba', 'name_ar' => 'كوبا'],
    ['code' => 'JAM', 'name_en' => 'Jamaica', 'name_ar' => 'جامايكا'],
];
