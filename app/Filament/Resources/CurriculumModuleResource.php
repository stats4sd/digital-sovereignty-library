<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurriculumModuleResource\Pages;
use App\Filament\Resources\CurriculumModuleResource\RelationManagers;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Models\CurriculumSessionItem;
use App\Support\HtmlSanitizer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CurriculumModuleResource extends Resource
{
    use Translatable;

    protected static ?string $model = CurriculumModule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Curriculum Modules';

    public ?string $activeLocale;

    /**
     * Only learning-map modules can be created (the create page fixes section = map); the
     * intro and toolkit rows are seeded and matched to fixed layout positions by key.
     */
    public static function isMapModule(?CurriculumModule $record, string $operation): bool
    {
        if ($operation === 'create') {
            return true;
        }

        return $record?->isMapModule() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Key')
                        ->helperText('Fixed identifier linking this module to its place in the curriculum layout and to its public URL. Generated from the English title on creation; not editable.')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create'),

                    Forms\Components\TextInput::make('section')
                        ->label('Section')
                        ->helperText('Where this module appears: the intro, the learning map, or the toolkit. Not editable.')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create'),

                    TranslatableComboField::make('title')
                        ->icon('heroicon-o-exclamation-circle')
                        ->iconColor('primary')
                        ->extraAttributes(['class' => 'grey-box'])
                        ->label('Title')
                        ->columns(['default' => 1, 'lg' => 2])
                        ->childField(Forms\Components\TextInput::class)
                        ->required(),

                    TranslatableComboField::make('subtitle')
                        ->icon('heroicon-o-bookmark')
                        ->iconColor('primary')
                        ->extraAttributes(['class' => 'grey-box'])
                        ->label('Subtitle')
                        ->description('Short category label shown on the toolkit pillar card (e.g. "Data & Knowledge Repositories").')
                        ->columns(['default' => 1, 'lg' => 2])
                        ->childField(Forms\Components\TextInput::class)
                        ->visible(fn (?CurriculumModule $record) => $record?->section === CurriculumModule::SECTION_TOOLKIT),

                    Forms\Components\TextInput::make('number')
                        ->label('Module number')
                        ->helperText('Sets the order of nodes on the learning map. Defaults to the next free number for a new module.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(255)
                        ->visible(fn (?CurriculumModule $record, string $operation) => static::isMapModule($record, $operation)),

                    Forms\Components\Toggle::make('is_draft')
                        ->label('Draft')
                        ->helperText('Hidden from learners (and the learning map) until switched off. Use Preview in the header to see the page as it will look.')
                        ->default(true)
                        ->visible(fn (?CurriculumModule $record, string $operation) => static::isMapModule($record, $operation)),

                    TranslatableComboField::make('goal')
                        ->icon('heroicon-o-flag')
                        ->iconColor('primary')
                        ->extraAttributes(['class' => 'grey-box'])
                        ->label('Goal')
                        ->childField(
                            Forms\Components\Textarea::make('goal')
                                ->rows(2),
                        )
                        ->visible(fn (?CurriculumModule $record, string $operation) => static::isMapModule($record, $operation)),

                    TranslatableComboField::make('description')
                        ->icon('heroicon-o-document-text')
                        ->iconColor('primary')
                        ->extraAttributes(['class' => 'grey-box'])
                        ->label('Description')
                        ->childField(
                            Forms\Components\RichEditor::make('description')
                                ->disableToolbarButtons([
                                    'attachFiles',
                                ])
                                ->dehydrateStateUsing(fn (?string $state): ?string => HtmlSanitizer::clean($state)),
                        ),

                    TranslatableComboField::make('note')
                        ->icon('heroicon-o-information-circle')
                        ->iconColor('primary')
                        ->extraAttributes(['class' => 'grey-box'])
                        ->label('Note')
                        ->description('Optional callout shown under the description (e.g. "This stop is entirely optional…").')
                        ->childField(
                            Forms\Components\Textarea::make('note')
                                ->rows(2),
                        ),

                    Repeater::make('learning_outcomes')
                        ->label('Learning Outcomes')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => data_get($state, 'statement.'.CurriculumModuleResource::getDefaultTranslatableLocale()))
                        ->addActionLabel('Add outcome')
                        ->defaultItems(0)
                        ->schema([
                            Hidden::make('key')
                                ->default(fn (): string => (string) Str::uuid()),

                            TranslatableComboField::make('statement')
                                ->label('Outcome')
                                ->columns(['default' => 1, 'lg' => 2])
                                ->childField(Forms\Components\TextInput::class)
                                ->required(),

                            TranslatableComboField::make('in_practice')
                                ->label('In practice')
                                ->childField(
                                    Forms\Components\Textarea::make('in_practice')
                                        ->rows(2),
                                ),
                        ]),
                ]),
            ])->columns(1);
    }

    /**
     * Human labels and colours for the section badge in the list.
     *
     * @return array<string, array{label: string, color: string}>
     */
    public static function sectionOptions(): array
    {
        return [
            CurriculumModule::SECTION_INTRO => ['label' => 'Intro', 'color' => 'gray'],
            CurriculumModule::SECTION_MAP => ['label' => 'Learning map', 'color' => 'primary'],
            CurriculumModule::SECTION_TOOLKIT => ['label' => 'Toolkit', 'color' => 'success'],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            // Intro first, then the learning map in node order, then the toolkit pillars.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE section WHEN 'intro' THEN 0 WHEN 'map' THEN 1 ELSE 2 END")
                ->orderByRaw('number IS NULL')
                ->orderBy('number')
                ->orderBy('id'))
            ->columns([
                Tables\Columns\TextColumn::make('section')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::sectionOptions()[$state]['label'] ?? $state)
                    ->color(fn (string $state): string => static::sectionOptions()[$state]['color'] ?? 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Map order')
                    ->placeholder('—')
                    ->alignCenter()
                    ->grow(false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->wrap(),
                Tables\Columns\TextColumn::make('is_draft')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CurriculumModule $record, bool $state): ?string => $record->isMapModule() ? ($state ? 'Draft' : 'Live') : null)
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('key')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('troves_count')
                    ->counts(['troves' => fn (Builder $query) => $query->workingVersions()])
                    ->label('# Resources')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sessions_count')
                    ->counts('sessions')
                    ->label('# Sessions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('section')
                    ->options(array_map(fn (array $option): string => $option['label'], static::sectionOptions())),
            ])
            ->recordActions([
                EditAction::make(),
                static::deleteAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Deleting a learning-map module cascades to its sessions and their content items; the
     * confirmation spells that out. The policy hides this for intro and toolkit rows.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (CurriculumModule $record): string => 'Delete module "'.$record->title.'"?')
            ->modalDescription(function (CurriculumModule $record): string {
                $sessions = $record->sessions()->count();
                $items = CurriculumSessionItem::query()
                    ->whereIn('curriculum_session_id', $record->sessions()->select('id'))
                    ->count();

                return "This removes the module from the learning map, along with its {$sessions} session(s) and {$items} content item(s). Learners' browser-side notes for those sessions become unreachable. This cannot be undone.";
            });
    }

    public static function getRelations(): array
    {
        return [
            'troves' => RelationManagers\TrovesRelationManager::class,
            'sessions' => RelationManagers\SessionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurriculumModules::route('/'),
            'create' => Pages\CreateCurriculumModule::route('/create'),
            'edit' => Pages\EditCurriculumModule::route('/{record}/edit'),
        ];
    }
}
