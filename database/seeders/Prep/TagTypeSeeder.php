<?php

namespace Database\Seeders\Prep;

use App\Models\TagType;
use Illuminate\Database\Seeder;

class TagTypeSeeder extends Seeder
{
    public function run(): void
    {
        TagType::create([
            'slug' => 'topics',
            'label' => [
                'en' => 'Topics',
                'id' => 'Topik',
                'es' => 'Temas',
                'fil' => 'Mga Paksa',
                'fr' => 'Sujets',
                'it' => 'Argomenti',
                'sw' => 'Mada',
                'pt' => 'Temas',
                'ru' => 'Темы',
                'hi' => 'विषय',
                'ar' => 'الموضوعات',
                'zh_CN' => '主题',
            ],
            'description' => [
                'en' => 'Topics of interest',
                'id' => 'Topik yang diminati',
                'es' => 'Temas de interés',
                'fil' => 'Mga paksa ng interes',
                'fr' => 'Sujets d\'intérêt',
                'it' => 'Argomenti di interesse',
                'sw' => 'Mada za kuvutia',
                'pt' => 'Temas de interesse',
                'ru' => 'Интересующие темы',
                'hi' => 'रुचि के विषय',
                'ar' => 'موضوعات ذات أهمية',
                'zh_CN' => '感兴趣的主题',
            ],
            'freetext' => false,
        ]);

        TagType::create([
            'slug' => 'authors',
            'label' => [
                'en' => 'Authors',
                'id' => 'Penulis',
                'es' => 'Autores',
                'fil' => 'Mga May-akda',
                'fr' => 'Auteurs',
                'it' => 'Autori',
                'sw' => 'Waandishi',
                'pt' => 'Autores',
                'ru' => 'Авторы',
                'hi' => 'लेखक',
                'ar' => 'المؤلفون',
                'zh_CN' => '作者',
            ],
            'description' => [
                'en' => 'Authors of content',
                'id' => 'Penulis konten',
                'es' => 'Autores del contenido',
                'fil' => 'Mga may-akda ng nilalaman',
                'fr' => 'Auteurs de contenu',
                'it' => 'Autori dei contenuti',
                'sw' => 'Waandishi wa maudhui',
                'pt' => 'Autores do conteúdo',
                'ru' => 'Авторы материалов',
                'hi' => 'सामग्री के लेखक',
                'ar' => 'مؤلفو المحتوى',
                'zh_CN' => '内容作者',
            ],
            'freetext' => true,
        ]);

        TagType::create([
            'slug' => 'locations',
            'label' => [
                'en' => 'Locations',
                'id' => 'Lokasi',
                'es' => 'Ubicaciones',
                'fil' => 'Mga Lokasyon',
                'fr' => 'Lieux',
                'it' => 'Luoghi',
                'sw' => 'Maeneo',
                'pt' => 'Localizações',
                'ru' => 'Местоположения',
                'hi' => 'स्थान',
                'ar' => 'المواقع',
                'zh_CN' => '地点',
            ],
            'description' => [
                'en' => 'Geographic locations',
                'id' => 'Lokasi geografis',
                'es' => 'Ubicaciones geográficas',
                'fil' => 'Mga lokasyong heograpiko',
                'fr' => 'Lieux géographiques',
                'it' => 'Luoghi geografici',
                'sw' => 'Maeneo ya kijiografia',
                'pt' => 'Localizações geográficas',
                'ru' => 'Географические местоположения',
                'hi' => 'भौगोलिक स्थान',
                'ar' => 'المواقع الجغرافية',
                'zh_CN' => '地理位置',
            ],
            'freetext' => false,
        ]);
    }
}
