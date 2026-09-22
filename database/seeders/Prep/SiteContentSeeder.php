<?php

namespace Database\Seeders\Prep;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Seeds the editable CMS strings with the content of the live Digital
 * Sovereignty site (snapshot taken 2026-07-09). Uses firstOrCreate on `key`,
 * so admin edits made via the Site Content page survive a re-seed.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $key => $value) {
            SiteContent::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function defaults(): array
    {
        $headingLine1 = [
            'en' => 'Digital Sovereignty',
            'es' => 'Soberanía digital',
            'fr' => 'Souveraineté numérique',
            'pt' => 'Soberania Digital',
            'id' => 'Kedaulatan Digital',
            'fil' => 'Digital na Soberanya',
            'it' => 'Sovranità digitale',
            'sw' => 'Uhuru wa Kidijitali',
            'ru' => 'Цифровой суверенитет',
            'hi' => 'डिजिटल संप्रभुता',
            'ar' => 'السيادة الرقمية',
            'zh_CN' => '数字主权',
        ];

        return [
            // Template home page (home.blade.php). Not routed on this deployment, but the
            // live database still carries the content, so it is kept in step here.
            'home_heading_line1' => $headingLine1,

            'home_heading_line2' => [
                'en' => 'Resources Library',
                'es' => 'Biblioteca de recursos de demostración',
                'fr' => 'Bibliothèque de ressources de démonstration',
                'pt' => 'Biblioteca de Recursos de Demonstração',
                'id' => 'Perpustakaan Sumber Daya',
                'fil' => 'Aklatan ng mga Sanggunian',
                'it' => 'Biblioteca delle risorse',
                'sw' => 'Maktaba ya Rasilimali',
                'ru' => 'Библиотека ресурсов',
                'hi' => 'संसाधन पुस्तकालय',
                'ar' => 'مكتبة الموارد',
                'zh_CN' => '资源库',
            ],

            'home_intro' => [
                'en' => "This library of resources is a demo and test of the library system for presenting resources developed for the digital sovereignty Praxis and eLearning courses.\n\nThe site is currently a demo, so you will find placeholder content, test issues and, of course, bugs.",
                'es' => "Esta biblioteca de recursos es una demostración y una prueba del sistema de gestión de contenidos diseñado para presentar los recursos desarrollados para los cursos «Praxis» y de aprendizaje electrónico sobre soberanía digital.\n\nActualmente, el sitio web es una versión de demostración, por lo que encontrarás contenido provisional, problemas de prueba y, por supuesto, errores.",
                'fr' => "Cette bibliothèque de ressources sert de démonstration et de test du système de gestion de contenu destiné à présenter les ressources développées pour les cours « Praxis » et d’apprentissage en ligne sur la souveraineté numérique.\n\nLe site est actuellement en version de démonstration ; vous y trouverez donc du contenu provisoire, des problèmes de test et, bien sûr, des bugs.",
                'pt' => "Esta biblioteca de recursos é uma demonstração e um teste do sistema de biblioteca destinado à apresentação dos recursos desenvolvidos para os cursos «Praxis» e de eLearning sobre soberania digital.\n\nO site encontra-se atualmente em fase de demonstração, pelo que irá encontrar conteúdo provisório, problemas de teste e, claro, erros.",
                'id' => "Perpustakaan sumber daya ini adalah demo dan uji coba sistem perpustakaan untuk menyajikan sumber daya yang dikembangkan untuk kursus Praxis dan eLearning kedaulatan digital.\n\nSitus ini saat ini masih berupa demo, sehingga Anda akan menemukan konten pengganti, masalah uji coba dan, tentu saja, bug.",
                'fil' => "Ang aklatang ito ng mga sanggunian ay isang demo at pagsubok ng sistema ng aklatan para sa pagpapakita ng mga sangguniang binuo para sa mga kursong Praxis at eLearning sa digital na soberanya.\n\nAng site ay kasalukuyang demo pa lamang, kaya makikita mo ang pansamantalang nilalaman, mga isyu sa pagsubok at, siyempre, mga bug.",
                'it' => "Questa biblioteca di risorse è una dimostrazione e un test del sistema di biblioteca per presentare le risorse sviluppate per i corsi Praxis ed eLearning sulla sovranità digitale.\n\nIl sito è attualmente una demo, quindi troverai contenuti provvisori, problemi di test e, naturalmente, bug.",
                'sw' => "Maktaba hii ya rasilimali ni onyesho na jaribio la mfumo wa maktaba kwa ajili ya kuwasilisha rasilimali zilizotengenezwa kwa kozi za Praxis na eLearning za uhuru wa kidijitali.\n\nKwa sasa tovuti hii ni onyesho tu, kwa hivyo utakuta maudhui ya muda, masuala ya majaribio na, bila shaka, hitilafu.",
                'ru' => "Эта библиотека ресурсов является демонстрацией и тестом библиотечной системы для представления ресурсов, разработанных для курсов Praxis и электронного обучения по цифровому суверенитету.\n\nСайт в настоящее время является демонстрационным, поэтому вы найдёте временное содержимое, тестовые проблемы и, конечно, ошибки.",
                'hi' => "संसाधनों की यह लाइब्रेरी डिजिटल संप्रभुता के प्रैक्सिस और ई-लर्निंग पाठ्यक्रमों के लिए विकसित संसाधनों को प्रस्तुत करने वाली लाइब्रेरी प्रणाली का एक डेमो और परीक्षण है।\n\nयह साइट वर्तमान में एक डेमो है, इसलिए आपको अस्थायी सामग्री, परीक्षण संबंधी समस्याएँ और, ज़ाहिर है, बग मिलेंगे।",
                'ar' => "مكتبة الموارد هذه هي نسخة تجريبية واختبار لنظام المكتبة المخصص لعرض الموارد المطوَّرة لدورات «براكسيس» والتعلم الإلكتروني حول السيادة الرقمية.\n\nالموقع حاليًا نسخة تجريبية، لذا ستجد محتوى مؤقتًا ومشكلات اختبار، وبالطبع أخطاء.",
                'zh_CN' => "这个资源库是一个演示和测试，用于展示为数字主权 Praxis 课程和在线学习课程开发的资源。\n\n本网站目前仍为演示版本，因此您会看到占位内容、测试问题，当然还有一些错误。",
            ],

            // Library / browse page (livewire/browse-all.blade.php).
            'library_heading_line1' => $headingLine1,

            'library_heading_line2' => [
                'en' => 'Demo Resources Library',
                'es' => 'Biblioteca de recursos de demostración',
                'fr' => 'Bibliothèque de ressources de démonstration',
                'pt' => 'Biblioteca de Recursos de Demonstração',
                'id' => 'Perpustakaan Sumber Daya Demo',
                'fil' => 'Demo na Aklatan ng mga Sanggunian',
                'it' => 'Biblioteca delle risorse dimostrativa',
                'sw' => 'Maktaba ya Rasilimali ya Majaribio',
                'ru' => 'Демонстрационная библиотека ресурсов',
                'hi' => 'डेमो संसाधन पुस्तकालय',
                'ar' => 'مكتبة الموارد التجريبية',
                'zh_CN' => '演示资源库',
            ],

            'library_hero_description' => [
                'en' => 'Browse the full library of resources and collections on what digital sovereignty means in an agricultural context.',
                'es' => 'Explora la biblioteca completa de recursos y colecciones sobre lo que significa la soberanía digital en un contexto agrícola.',
                'fr' => 'Parcourez l’intégralité de la bibliothèque de ressources et de collections pour découvrir ce que signifie la souveraineté numérique dans un contexte agricole.',
                'pt' => 'Navegue pela biblioteca completa de recursos e coleções sobre o que significa a soberania digital num contexto agrícola.',
                'id' => 'Jelajahi seluruh perpustakaan sumber daya dan koleksi tentang apa arti kedaulatan digital dalam konteks pertanian.',
                'fil' => 'Tuklasin ang buong aklatan ng mga sanggunian at koleksyon tungkol sa kahulugan ng digital na soberanya sa konteksto ng agrikultura.',
                'it' => 'Esplora l\'intera biblioteca di risorse e raccolte su cosa significa la sovranità digitale in un contesto agricolo.',
                'sw' => 'Vinjari maktaba kamili ya rasilimali na mikusanyo kuhusu maana ya uhuru wa kidijitali katika muktadha wa kilimo.',
                'ru' => 'Просмотрите полную библиотеку ресурсов и подборок о том, что означает цифровой суверенитет в сельскохозяйственном контексте.',
                'hi' => 'कृषि के संदर्भ में डिजिटल संप्रभुता का क्या अर्थ है, इस पर संसाधनों और संग्रहों की पूरी लाइब्रेरी ब्राउज़ करें।',
                'ar' => 'تصفح المكتبة الكاملة من الموارد والمجموعات حول معنى السيادة الرقمية في السياق الزراعي.',
                'zh_CN' => '浏览完整的资源与合集库，了解数字主权在农业背景下的含义。',
            ],

            // Footer (footer.blade.php).
            'footer_admin_login_label' => [
                'en' => 'Login',
                'es' => 'Iniciar sesión',
                'fr' => 'Connexion',
                'pt' => 'Iniciar sessão',
                'id' => 'Masuk',
                'fil' => 'Mag-login',
                'it' => 'Accedi',
                'sw' => 'Ingia',
                'ru' => 'Войти',
                'hi' => 'लॉगिन',
                'ar' => 'تسجيل الدخول',
                'zh_CN' => '登录',
            ],
        ];
    }
}
