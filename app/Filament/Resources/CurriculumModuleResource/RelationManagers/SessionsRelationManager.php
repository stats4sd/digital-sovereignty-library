<?php

namespace App\Filament\Resources\CurriculumModuleResource\RelationManagers;

use App\Filament\Resources\CurriculumSessionResource;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Support\TranslatableText;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable;
use Livewire\Attributes\Reactive;

class SessionsRelationManager extends RelationManager
{
    use Translatable;

    #[Reactive]
    public ?string $activeLocale = null;

    protected static string $relationship = 'sessions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->section === CurriculumModule::SECTION_MAP;
    }

    /**
     * The Translatable concern (via HasActiveLocaleSwitcher) defaults this to the lara-zeus
     * SpatieTranslatableContentDriver, which re-wraps whatever state each field already holds in
     * setTranslation($attr, $activeLocale, $value) on save. The create form here uses
     * TranslatableComboField, whose state is already a full locale dictionary (e.g. ['en' =>
     * '…', 'fr' => '…']), so the driver would nest that dictionary under $activeLocale again,
     * corrupting the stored JSON. Returning null disables the driver; the trait is kept only for
     * #[Reactive] $activeLocale, and the table columns below read translations explicitly
     * instead of relying on the driver/app locale.
     */
    public function getFilamentTranslatableContentDriver(): ?string
    {
        return null;
    }

    /**
     * Filament's HasRecords::getTableRecord() calls
     * makeFilamentTranslatableContentDriver()->setRecordLocale() whenever this is filled, so
     * with the driver nulled above every record action (e.g. delete) would fatal with
     * "setRecordLocale() on null". The columns read $this->activeLocale directly, so nothing
     * here needs the table-level locale.
     */
    public function getActiveTableLocale(): ?string
    {
        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sessions')
            ->recordTitleAttribute('title')
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('#'),
                Tables\Columns\TextColumn::make('title')
                    ->wrap()
                    ->formatStateUsing(fn (CurriculumSession $record): ?string => TranslatableText::pick(
                        $record->getTranslations('title'),
                        $this->activeLocale ?? app()->getLocale(),
                    )),
                Tables\Columns\TextColumn::make('summary')
                    ->limit(60)
                    ->formatStateUsing(fn (CurriculumSession $record): ?string => TranslatableText::pick(
                        $record->getTranslations('summary'),
                        $this->activeLocale ?? app()->getLocale(),
                    )),
                Tables\Columns\TextColumn::make('builds_toward')
                    ->label('Builds toward')
                    ->formatStateUsing(function (CurriculumSession $record): ?string {
                        $outcome = $record->builds_toward_outcome;

                        return $outcome === null ? null : 'LO'.$outcome['position'].' — '.$outcome['statement'];
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('# Content blocks'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add session')
                    ->modalHeading('Add a session to this module')
                    ->schema(fn () => CurriculumSessionResource::formSchema($this->getOwnerRecord()))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['order_column'] = ($this->getOwnerRecord()->sessions()->max('order_column') ?? 0) + 1;

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('content')
                    ->label('Edit Content')
                    ->icon('heroicon-o-queue-list')
                    ->url(fn (CurriculumSession $record) => CurriculumSessionResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateDescription('Use the "Add session" button above to create an ordered stop for this module. Drag rows to set the order they appear in.');
    }
}
