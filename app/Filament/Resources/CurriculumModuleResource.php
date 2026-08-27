<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurriculumModuleResource\Pages;
use App\Filament\Resources\CurriculumModuleResource\RelationManagers;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Support\HtmlSanitizer;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CurriculumModuleResource extends Resource
{
    use Translatable;

    protected static ?string $model = CurriculumModule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Curriculum Modules';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Key')
                    ->helperText('Fixed identifier linking this module to its place in the curriculum layout. Not editable.')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('section')
                    ->label('Section')
                    ->helperText('Where this module appears: the intro, the learning map, or the toolkit. Not editable.')
                    ->disabled()
                    ->dehydrated(false),

                TranslatableComboField::make('title')
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Title')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),

                TranslatableComboField::make('subtitle')
                    ->icon('heroicon-o-bookmark')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Subtitle')
                    ->description('Short category label shown on the toolkit pillar card (e.g. "Data & Knowledge Repositories").')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->visible(fn (?CurriculumModule $record) => $record?->section === CurriculumModule::SECTION_TOOLKIT),

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

                TranslatableComboField::make('learning_outcomes')
                    ->icon('heroicon-o-check-circle')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Learning Outcomes')
                    ->description('One outcome per line. Shown as a bullet list on the module page.')
                    ->childField(
                        Forms\Components\Textarea::make('learning_outcomes')
                            ->rows(5),
                    ),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('section')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('section')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->wrap(),
                Tables\Columns\TextColumn::make('troves_count')
                    ->counts(['troves' => fn (Builder $query) => $query->workingVersions()])
                    ->label('# Resources')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('section')
                    ->options([
                        CurriculumModule::SECTION_INTRO => 'Intro',
                        CurriculumModule::SECTION_MAP => 'Learning Map',
                        CurriculumModule::SECTION_TOOLKIT => 'Toolkit',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TrovesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurriculumModules::route('/'),
            'edit' => Pages\EditCurriculumModule::route('/{record}/edit'),
        ];
    }
}
