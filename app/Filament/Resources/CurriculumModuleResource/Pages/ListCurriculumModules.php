<?php

namespace App\Filament\Resources\CurriculumModuleResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Translatable\TranslatableListView;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class ListCurriculumModules extends ListRecords
{
    use TranslatableListView;

    protected static string $resource = CurriculumModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
