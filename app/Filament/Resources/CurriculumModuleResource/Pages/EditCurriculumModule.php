<?php

namespace App\Filament\Resources\CurriculumModuleResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditCurriculumModule extends EditRecord
{
    use Translatable;

    protected static string $resource = CurriculumModuleResource::class;

    // Relation managers declare `activeLocale` as a reactive prop and cannot
    // set it themselves; ensure it's populated before Filament builds the
    // relation manager schema, or they'll try to mutate the reactive prop.
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (blank($this->activeLocale)) {
            $this->activeLocale = static::getResource()::getDefaultTranslatableLocale();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
