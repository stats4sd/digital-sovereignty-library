<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use Filament\Forms\Components\RichEditor;

/**
 * A block of explanatory rich text with an optional heading.
 */
class ProseItem extends ItemDefinition
{
    public function type(): CurriculumItemType
    {
        return CurriculumItemType::Prose;
    }

    public function configKeys(): array
    {
        return ['heading', 'body'];
    }

    public function translatableLeaves(): array
    {
        return ['heading', 'body'];
    }

    public function htmlLeaves(): array
    {
        return ['body'];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'array'],
            'heading.*' => ['string'],
            'body' => ['required', 'array'],
            'body.*' => ['string'],
        ];
    }

    protected function fields(): array
    {
        return [
            $this->headingField(required: false),

            $this->translatable(
                'body',
                'Text',
                RichEditor::make('body')->disableToolbarButtons(['attachFiles']),
                required: true,
            ),
        ];
    }
}
