<?php

namespace App\Filament\Resources\CurriculumModuleResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditCurriculumModule extends EditRecord
{
    use Translatable;

    protected static string $resource = CurriculumModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
