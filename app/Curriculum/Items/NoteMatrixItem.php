<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Support\TranslatableText;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rule;

/**
 * A grid of `rows` numbered rows × one column per `field` (free text or a select). Learner
 * state: `{key}.{row}.{field.id}`.
 */
class NoteMatrixItem extends ItemDefinition
{
    public const FIELD_KINDS = [
        'text' => 'Free text',
        'select' => 'Choice list',
    ];

    public function type(): CurriculumItemType
    {
        return CurriculumItemType::NoteMatrix;
    }

    public function configKeys(): array
    {
        return ['heading', 'rows', 'rowLabel', 'fields'];
    }

    public function translatableLeaves(): array
    {
        return [
            'heading',
            'rowLabel',
            'fields.*.label',
            'fields.*.placeholder',
            'fields.*.options.*.text',
        ];
    }

    public function integerLeaves(): array
    {
        return ['rows'];
    }

    public function listLeaves(): array
    {
        return ['fields', 'fields.*.options'];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'array'],
            'heading.*' => ['string'],
            'rows' => ['required', 'integer', 'min:1', 'max:20'],
            'rowLabel' => ['nullable', 'array'],
            'rowLabel.*' => ['string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [$this->uniqueChoiceIdsRule()],
            'fields.*.id' => ['required', 'string', 'alpha_dash', 'max:40', 'distinct'],
            'fields.*.label' => ['required', 'array'],
            'fields.*.label.*' => ['string'],
            'fields.*.placeholder' => ['nullable', 'array'],
            'fields.*.placeholder.*' => ['string'],
            'fields.*.kind' => ['required', Rule::in(array_keys(self::FIELD_KINDS))],
            'fields.*.options' => ['array', 'required_if:fields.*.kind,select'],
            // Choice ids only need to be unique within their column; checked in uniqueChoiceIdsRule().
            'fields.*.options.*.id' => ['required', 'string', 'alpha_dash', 'max:40'],
            'fields.*.options.*.text' => ['required', 'array'],
            'fields.*.options.*.text.*' => ['string'],
        ];
    }

    private function uniqueChoiceIdsRule(): Closure
    {
        return function (string $attribute, mixed $field, Closure $fail): void {
            if (! is_array($field)) {
                return;
            }

            $ids = collect($field['options'] ?? [])->filter(fn ($o) => is_array($o))->pluck('id')->filter();

            if ($ids->count() !== $ids->unique()->count()) {
                $fail('Each choice in a column needs a different ID.');
            }
        };
    }

    protected function fields(): array
    {
        return [
            $this->headingField(required: true),

            TextInput::make('rows')
                ->label('Number of rows')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(20)
                ->default(3)
                ->required(),

            $this->translatable('rowLabel', 'Row label', TextInput::class)
                ->description('Shown with the row number, e.g. "Challenge" → "Challenge 1".'),

            $this->listRepeater('fields', 'Columns', 'Add column', level: 1)
                ->collapsible()
                ->defaultItems(1)
                ->minItems(1)
                ->itemLabel(fn (array $state): ?string => TranslatableText::pick(is_array($state['label'] ?? null) ? $state['label'] : null))
                ->schema([
                    $this->idField('answers in this column'),
                    $this->translatable('label', 'Label', TextInput::class, required: true),
                    Select::make('kind')
                        ->label('Input')
                        ->options(self::FIELD_KINDS)
                        ->default('text')
                        ->required()
                        ->native(false)
                        ->live(),
                    $this->translatable('placeholder', 'Placeholder', TextInput::class)
                        ->visible(fn (Get $get): bool => $get('kind') !== 'select'),
                    $this->listRepeater('options', 'Choices', 'Add choice', level: 2)
                        ->defaultItems(2)
                        ->itemLabel(fn (array $state): ?string => TranslatableText::pick(is_array($state['text'] ?? null) ? $state['text'] : null))
                        ->visible(fn (Get $get): bool => $get('kind') === 'select')
                        ->schema([
                            $this->idField('choice'),
                            $this->translatable('text', 'Text', TextInput::class, required: true),
                        ]),
                ]),
        ];
    }
}
