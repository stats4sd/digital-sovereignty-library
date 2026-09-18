<?php

namespace App\Filament\Resources\CurriculumSessionResource\RelationManagers;

use App\Models\Trove;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable;
use Livewire\Attributes\Reactive;

class TrovesRelationManager extends RelationManager
{
    use Translatable;

    #[Reactive]
    public ?string $activeLocale = null;

    protected static string $relationship = 'troves';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Resources in this Session')
            ->recordTitleAttribute('title')
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->wrap(),
                Tables\Columns\TextColumn::make('troveType.label')
                    ->label('Type'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add resource')
                    ->modalHeading('Add a resource to this session')
                    ->recordSelectSearchColumns(['title'])
                    ->recordSelectOptionsQuery(
                        fn (Builder $query) => $query
                            ->whereNotNull('published_at')
                            ->whereNull('published_id')
                    )
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove from session')
                    ->modalHeading('Remove resource from session'),
            ])
            ->toolbarActions([])
            ->recordUrl(fn (Trove $record) => url('/resources/'.$record->slug))
            ->emptyStateDescription('Use the "Add resource" button above to link published resources to this session. Drag rows to set the order they appear in.');
    }
}
