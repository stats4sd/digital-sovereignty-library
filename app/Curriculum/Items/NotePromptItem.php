<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * One free-text answer to a prompt. Learner state: `{key}`.
 */
class NotePromptItem extends ItemDefinition
{
    public function type(): CurriculumItemType
    {
        return CurriculumItemType::NotePrompt;
    }

    public function configKeys(): array
    {
        return ['heading', 'label', 'prompt', 'placeholder', 'rows'];
    }

    public function translatableLeaves(): array
    {
        return ['heading', 'label', 'prompt', 'placeholder'];
    }

    public function integerLeaves(): array
    {
        return ['rows'];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'array'],
            'heading.*' => ['string'],
            'label' => ['nullable', 'array'],
            'label.*' => ['string'],
            'prompt' => ['required', 'array'],
            'prompt.*' => ['string'],
            'placeholder' => ['nullable', 'array'],
            'placeholder.*' => ['string'],
            'rows' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }

    protected function fields(): array
    {
        return [
            $this->headingField(required: true),

            $this->translatable('prompt', 'Prompt', Textarea::make('prompt')->rows(3), required: true),

            $this->translatable('label', 'Answer label', TextInput::class)
                ->description('Label above the answer box, e.g. "Your diagnostic statement".'),

            $this->translatable('placeholder', 'Placeholder', TextInput::class),

            TextInput::make('rows')
                ->label('Answer box height (rows)')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(40)
                ->default(4)
                ->required(),
        ];
    }
}
