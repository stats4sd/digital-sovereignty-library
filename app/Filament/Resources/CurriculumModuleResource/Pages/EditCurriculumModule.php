<?php

namespace App\Filament\Resources\CurriculumModuleResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditCurriculumModule extends EditRecord
{
    use Translatable;

    protected static string $resource = CurriculumModuleResource::class;

    public ?string $activeLocale = null;

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

    /**
     * One "Module" tab for the module's own fields, then a tab per relation manager
     * (Sessions for map modules, Resources for intro/toolkit), mirroring the session edit page.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Module';
    }

    public function getContentTabIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'heroicon-o-information-circle';
    }

    protected function getHeaderActions(): array
    {
        return [
            CurriculumModuleResource::deleteAction(),
        ];
    }
}
