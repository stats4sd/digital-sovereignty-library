<?php

namespace App\Filament\Resources\CurriculumSessionResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Resources\CurriculumSessionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditCurriculumSession extends EditRecord
{
    use Translatable;

    protected static string $resource = CurriculumSessionResource::class;

    public ?string $activeLocale = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->activeLocale = static::getResource()::getDefaultTranslatableLocale();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToModule')
                ->label('Back to module')
                ->url(fn () => CurriculumModuleResource::getUrl('edit', ['record' => $this->record->curriculum_module_id])),
        ];
    }
}
