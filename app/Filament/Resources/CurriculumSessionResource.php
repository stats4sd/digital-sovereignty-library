<?php

namespace App\Filament\Resources;

use App\Filament\Components\ErrorBadgedTab;
use App\Filament\Curriculum\SessionContentBuilder;
use App\Filament\Resources\CurriculumSessionResource\Pages;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Support\HtmlSanitizer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CurriculumSessionResource extends Resource
{
    use Translatable;

    protected static ?string $model = CurriculumSession::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Session')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        ErrorBadgedTab::make('Session')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Placeholder::make('module')
                                    ->label('Module')
                                    ->content(function (?CurriculumSession $record): HtmlString {
                                        $module = $record?->module;

                                        if ($module === null) {
                                            return new HtmlString('');
                                        }

                                        $url = CurriculumModuleResource::getUrl('edit', ['record' => $module]);

                                        return new HtmlString('<a href="'.$url.'" class="underline">'.e($module->title).'</a>');
                                    }),

                                ...static::formSchema(),

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
                            ]),

                        ErrorBadgedTab::make('Content')
                            ->icon('heroicon-o-queue-list')
                            ->schema([
                                SessionContentBuilder::make('content'),
                            ]),
                    ]),
            ])->columns(1);
    }

    /**
     * The session field set shared between this resource's edit form and the
     * SessionsRelationManager's create modal on the module. When $module is
     * given (the relation manager already knows its owner record) it is used
     * directly for the "builds toward" options; otherwise they fall back to the
     * session record's own module, injected by Filament at render time.
     */
    public static function formSchema(?CurriculumModule $module = null): array
    {
        return [
            TranslatableComboField::make('title')
                ->icon('heroicon-o-exclamation-circle')
                ->iconColor('primary')
                ->extraAttributes(['class' => 'grey-box'])
                ->label('Title')
                ->columns(3)
                ->childField(Forms\Components\TextInput::class)
                ->required(),

            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->helperText(fn (string $operation): string => $operation === 'create'
                    ? 'Leave blank to generate from the title. Cannot be changed after creation.'
                    : 'Fixed identifier used in this session\'s public URL. Not editable.')
                ->disabledOn('edit')
                ->dehydrated(fn (string $operation): bool => $operation === 'create')
                ->maxLength(255)
                ->rule('alpha_dash')
                ->mutateStateForValidationUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                ->unique(
                    table: 'curriculum_sessions',
                    column: 'slug',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, ?CurriculumSession $record): Unique => $rule->where(
                        'curriculum_module_id',
                        $record?->curriculum_module_id ?? $module?->id,
                    ),
                )
                ->nullable(),

            TranslatableComboField::make('summary')
                ->icon('heroicon-o-bookmark')
                ->iconColor('primary')
                ->extraAttributes(['class' => 'grey-box'])
                ->label('Summary')
                ->childField(
                    Forms\Components\Textarea::make('summary')
                        ->rows(2),
                ),

            Forms\Components\Select::make('builds_toward')
                ->label('Builds toward')
                ->options(function (?CurriculumSession $record) use ($module): array {
                    $module ??= $record?->module;

                    return collect($module?->outcomes_list ?? [])
                        ->mapWithKeys(fn (array $outcome, int $position) => [
                            $outcome['key'] => ($position + 1).'. '.$outcome['statement'],
                        ])
                        ->all();
                })
                ->placeholder('None')
                ->native(false)
                ->nullable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('curriculum_module_id')
                ->orderBy('order_column'))
            ->columns([
                Tables\Columns\TextColumn::make('module.title')
                    ->label('Module'),
                Tables\Columns\TextColumn::make('title')
                    ->wrap(),
                Tables\Columns\TextColumn::make('number')
                    ->label('#'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('# Content blocks'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
            ])
            ->recordUrl(fn (CurriculumSession $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurriculumSessions::route('/'),
            'edit' => Pages\EditCurriculumSession::route('/{record}/edit'),
        ];
    }
}
