<?php

namespace Database\Seeders\Prep;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::firstOrCreate(['id' => 1], [
            'show_language_filter' => true,
            'show_trove_type_filter' => false,
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'id', 'label' => 'Bahasa Indonesia'],
                ['code' => 'es', 'label' => 'Español'],
                ['code' => 'fil', 'label' => 'Filipino'],
                ['code' => 'fr', 'label' => 'Français'],
                ['code' => 'it', 'label' => 'Italiano'],
                ['code' => 'sw', 'label' => 'Kiswahili'],
                ['code' => 'pt', 'label' => 'Português'],
                ['code' => 'ru', 'label' => 'Русский'],
                ['code' => 'hi', 'label' => 'हिन्दी'],
                ['code' => 'ar', 'label' => 'العربية'],
                ['code' => 'zh_CN', 'label' => '简体中文'],
            ],
        ]);
    }
}
