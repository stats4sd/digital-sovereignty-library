<?php

namespace Database\Seeders\Prep;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $orgName = config('branding.org_name', '');

        if ($orgName === '' || $orgName === 'Your Organisation') {
            $this->command?->warn('BRAND_ORG_NAME is not set (or still the "Your Organisation" default). Set it in .env before seeding, otherwise the placeholder name is baked into SiteContent and changing .env later will not update it.');
        }

        $defaults = [
            // The organisation name is a proper noun; other locales fall back to this value.
            'library_heading_line1' => ['en' => $orgName],

            'library_heading_line2' => [
                'en' => 'Resources Library',
                'id' => 'Perpustakaan Sumber Daya',
                'es' => 'Biblioteca de recursos',
                'fil' => 'Aklatan ng mga Sanggunian',
                'fr' => 'Bibliothèque de ressources',
                'it' => 'Biblioteca delle risorse',
                'sw' => 'Maktaba ya Rasilimali',
                'pt' => 'Biblioteca de recursos',
                'ru' => 'Библиотека ресурсов',
                'hi' => 'संसाधन पुस्तकालय',
                'ar' => 'مكتبة الموارد',
                'zh_CN' => '资源库',
            ],

            'library_hero_description' => [
                'en' => 'Browse the full library of resources and collections on a variety of topics.',
                'id' => 'Jelajahi seluruh perpustakaan sumber daya dan koleksi tentang berbagai topik.',
                'es' => 'Explora la biblioteca completa de recursos y colecciones sobre diversos temas.',
                'fil' => 'Tuklasin ang buong aklatan ng mga sanggunian at koleksyon sa iba\'t ibang paksa.',
                'fr' => 'Parcourez l\'ensemble de la bibliothèque de ressources et de collections sur des sujets variés.',
                'it' => 'Esplora l\'intera biblioteca di risorse e raccolte su una varietà di argomenti.',
                'sw' => 'Vinjari maktaba kamili ya rasilimali na mikusanyo kuhusu mada mbalimbali.',
                'pt' => 'Explore a biblioteca completa de recursos e coleções sobre diversos temas.',
                'ru' => 'Просмотрите полную библиотеку ресурсов и подборок по самым разным темам.',
                'hi' => 'विभिन्न विषयों पर संसाधनों और संग्रहों की पूरी लाइब्रेरी ब्राउज़ करें।',
                'ar' => 'تصفح المكتبة الكاملة من الموارد والمجموعات حول مواضيع متنوعة.',
                'zh_CN' => '浏览涵盖各类主题的完整资源与合集库。',
            ],

            'footer_admin_login_label' => [
                'en' => 'Staff Login',
                'id' => 'Masuk Staf',
                'es' => 'Acceso del personal',
                'fil' => 'Pag-login ng Kawani',
                'fr' => 'Connexion du personnel',
                'it' => 'Accesso per lo staff',
                'sw' => 'Kuingia kwa Wafanyakazi',
                'pt' => 'Acesso da equipa',
                'ru' => 'Вход для сотрудников',
                'hi' => 'स्टाफ़ लॉगिन',
                'ar' => 'تسجيل دخول الموظفين',
                'zh_CN' => '员工登录',
            ],
        ];

        foreach ($defaults as $key => $value) {
            SiteContent::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
