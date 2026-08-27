<?php

namespace App\Filament\Resources\GlossaryTermResource\Pages;

use App\Filament\Resources\GlossaryTermResource;
use App\Filament\Translatable\TranslatableListView;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class ListGlossaryTerms extends ManageRecords
{
    use TranslatableListView;

    protected static string $resource = GlossaryTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            LocaleSwitcher::make(),
        ];
    }
}
