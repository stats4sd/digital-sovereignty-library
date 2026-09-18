<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlossaryTermResource\Pages;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\GlossaryTerm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class GlossaryTermResource extends Resource
{
    use Translatable;

    protected static ?string $model = GlossaryTerm::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Glossary';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TranslatableComboField::make('term')
                    ->icon('heroicon-o-book-open')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Term')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),

                TranslatableComboField::make('definition')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Definition')
                    ->description('A short definition. Shown in tooltips and the glossary drawer.')
                    ->childField(
                        Forms\Components\Textarea::make('definition')
                            ->rows(3),
                    )
                    ->required(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('term->'.app()->getLocale())
            ->columns([
                Tables\Columns\TextColumn::make('term')
                    ->searchable(),
                Tables\Columns\TextColumn::make('definition')
                    ->limit(90)
                    ->wrap(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading(fn (GlossaryTerm $record): string => 'Delete glossary term "'.$record->term.'"'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlossaryTerms::route('/'),
        ];
    }
}
