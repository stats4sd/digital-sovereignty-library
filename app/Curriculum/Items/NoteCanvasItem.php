<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Support\TranslatableText;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;

/**
 * A worksheet table: one row per `field`, each with a label, a guiding prompt and a notes
 * box. Learner state: `{key}.{field.id}`.
 */
class NoteCanvasItem extends ItemDefinition
{
    public function type(): CurriculumItemType
    {
        return CurriculumItemType::NoteCanvas;
    }

    public function configKeys(): array
    {
        return ['heading', 'columns', 'fields'];
    }

    public function translatableLeaves(): array
    {
        return [
            'heading',
            'columns.label',
            'columns.prompt',
            'columns.notes',
            'fields.*.label',
            'fields.*.prompt',
        ];
    }

    public function listLeaves(): array
    {
        return ['fields'];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'array'],
            'heading.*' => ['string'],
            'columns' => ['nullable', 'array'],
            'columns.label' => ['nullable', 'array'],
            'columns.prompt' => ['nullable', 'array'],
            'columns.notes' => ['nullable', 'array'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['required', 'string', 'alpha_dash', 'max:40', 'distinct'],
            'fields.*.label' => ['required', 'array'],
            'fields.*.label.*' => ['string'],
            'fields.*.prompt' => ['nullable', 'array'],
            'fields.*.prompt.*' => ['string'],
        ];
    }

    protected function fields(): array
    {
        return [
            $this->headingField(required: true),

            Fieldset::make('Column headings')
                ->statePath('columns')
                ->columns(1)
                ->schema([
                    $this->translatable('label', 'First column (area)', TextInput::class),
                    $this->translatable('prompt', 'Second column (guiding question)', TextInput::class),
                    $this->translatable('notes', 'Third column (notes)', TextInput::class),
                ]),

            Repeater::make('fields')
                ->label('Rows')
                ->addActionLabel('Add row')
                ->reorderable()
                ->collapsible()
                ->defaultItems(1)
                ->minItems(1)
                ->itemLabel(fn (array $state): ?string => TranslatableText::pick(is_array($state['label'] ?? null) ? $state['label'] : null))
                ->schema([
                    $this->idField('notes for this row'),
                    $this->translatable('label', 'Label', TextInput::class, required: true),
                    $this->translatable('prompt', 'Guiding question', Textarea::make('prompt')->rows(2)),
                ]),
        ];
    }
}
