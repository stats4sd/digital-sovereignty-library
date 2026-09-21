<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Validation\Rule;

/**
 * A highlighted aside: a recall of earlier material, a worked example, key takeaways, or a
 * check. `lesson` is the one-line "so what" shown with example callouts.
 */
class CalloutItem extends ItemDefinition
{
    public const KINDS = [
        'recall' => 'Recall',
        'example' => 'Example',
        'takeaways' => 'Key takeaways',
        'check' => 'Check',
    ];

    public function type(): CurriculumItemType
    {
        return CurriculumItemType::Callout;
    }

    public function configKeys(): array
    {
        return ['kind', 'heading', 'body', 'lesson'];
    }

    public function translatableLeaves(): array
    {
        return ['heading', 'body', 'lesson'];
    }

    public function htmlLeaves(): array
    {
        return ['body'];
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'heading' => ['nullable', 'array'],
            'heading.*' => ['string'],
            'body' => ['required', 'array'],
            'body.*' => ['string'],
            'lesson' => ['nullable', 'array'],
            'lesson.*' => ['string'],
        ];
    }

    public function blockLabel(array $state): string
    {
        $kind = self::KINDS[$state['kind'] ?? ''] ?? null;
        $label = parent::blockLabel($state);

        return $kind ? str_replace($this->type()->label(), $this->type()->label().' ('.$kind.')', $label) : $label;
    }

    protected function fields(): array
    {
        return [
            Select::make('kind')
                ->label('Kind')
                ->options(self::KINDS)
                ->default('recall')
                ->required()
                ->native(false),

            $this->headingField(required: false),

            $this->translatable(
                'body',
                'Text',
                RichEditor::make('body')->disableToolbarButtons(['attachFiles']),
                required: true,
            ),

            $this->translatable('lesson', 'Lesson', Textarea::make('lesson')->rows(2))
                ->description('Optional one-line "the lesson here is…" shown under an example.'),
        ];
    }
}
