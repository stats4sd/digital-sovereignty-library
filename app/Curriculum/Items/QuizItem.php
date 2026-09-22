<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use Closure;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A self-check quiz scored in the browser: pick-one / pick-many questions with exact-set
 * marking, a pass mark and unlimited attempts. Learner state: `{key}.attempts`. The answer
 * key (`correct` flags) is part of the page payload by design (spec D4).
 */
class QuizItem extends ItemDefinition
{
    public const QUESTION_KINDS = [
        'pick-one' => 'Pick one',
        'pick-many' => 'Pick all that apply',
    ];

    public function type(): CurriculumItemType
    {
        return CurriculumItemType::Quiz;
    }

    public function configKeys(): array
    {
        return ['heading', 'passMark', 'items'];
    }

    public function translatableLeaves(): array
    {
        return [
            'heading',
            'items.*.stem',
            'items.*.options.*.text',
            'items.*.feedback.correct',
            'items.*.feedback.incorrect',
        ];
    }

    public function integerLeaves(): array
    {
        return ['passMark'];
    }

    public function booleanLeaves(): array
    {
        return ['items.*.options.*.correct'];
    }

    public function listLeaves(): array
    {
        return ['items', 'items.*.options'];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'array'],
            'heading.*' => ['string'],
            'passMark' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => [$this->correctOptionsRule()],
            'items.*.id' => ['required', 'string', 'alpha_dash', 'max:40', 'distinct'],
            'items.*.kind' => ['required', Rule::in(array_keys(self::QUESTION_KINDS))],
            'items.*.stem' => ['required', 'array'],
            'items.*.stem.*' => ['string'],
            'items.*.options' => ['required', 'array', 'min:2'],
            // Option ids only need to be unique within their question (learner state is
            // `{key}.attempts`), so uniqueness is checked per question in correctOptionsRule().
            'items.*.options.*.id' => ['required', 'string', 'alpha_dash', 'max:40'],
            'items.*.options.*.text' => ['required', 'array'],
            'items.*.options.*.text.*' => ['string'],
            'items.*.options.*.correct' => ['required', 'boolean'],
            'items.*.feedback' => ['nullable', 'array'],
            'items.*.feedback.correct' => ['nullable', 'array'],
            'items.*.feedback.incorrect' => ['nullable', 'array'],
        ];
    }

    protected function validateNormalised(array $config): void
    {
        if (($config['passMark'] ?? 0) > count($config['items'] ?? [])) {
            throw ValidationException::withMessages([
                'passMark' => 'The pass mark cannot exceed the number of questions.',
            ]);
        }
    }

    private function correctOptionsRule(): Closure
    {
        return function (string $attribute, mixed $question, Closure $fail): void {
            if (! is_array($question)) {
                return;
            }

            $options = collect($question['options'] ?? [])->filter(fn ($option) => is_array($option));

            $ids = $options->pluck('id')->filter();

            if ($ids->count() !== $ids->unique()->count()) {
                $fail('Each option in a question needs a different ID.');
            }

            $correct = $options->filter(fn (array $option) => $option['correct'] ?? false)->count();

            if ($correct === 0) {
                $fail('Each question needs at least one correct option.');
            } elseif (($question['kind'] ?? null) === 'pick-one' && $correct !== 1) {
                $fail('A pick-one question must have exactly one correct option.');
            }
        };
    }

    protected function fields(): array
    {
        return [
            $this->headingField(required: true),

            TextInput::make('passMark')
                ->label('Pass mark')
                ->helperText('Number of questions that must be answered correctly to pass.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->default(1)
                ->required(),

            $this->listRepeater('items', 'Questions', 'Add question', level: 1)
                ->collapsible()
                // Collapsed by default: a quiz block reads as a list of question headers and
                // one question is opened at a time, which caps the visible nesting depth.
                ->collapsed()
                ->defaultItems(1)
                ->minItems(1)
                ->itemLabel(fn (array $state, int $index): string => $this->numberedLabel('Q', $index, $state['stem'] ?? null))
                ->schema([
                    $this->idField('attempts'),
                    Select::make('kind')
                        ->label('Kind')
                        ->options(self::QUESTION_KINDS)
                        ->default('pick-one')
                        ->required()
                        ->native(false),
                    $this->translatable('stem', 'Question', Textarea::make('stem')->rows(2), required: true, inline: true),
                    $this->tableRepeater('options', 'Options', 'Add option', [
                        TableColumn::make('ID')->markAsRequired()->width('9rem'),
                        TableColumn::make('Text')->markAsRequired(),
                        TableColumn::make('Correct')->width('7rem'),
                    ])
                        ->helperText(static::idHelperText('answers'))
                        ->defaultItems(2)
                        ->minItems(2)
                        ->schema([
                            $this->idField('answers', withHelperText: false),
                            $this->translatable('text', 'Text', TextInput::class, required: true, inline: true),
                            Toggle::make('correct')
                                ->label('Correct answer')
                                ->default(false),
                        ]),
                    Fieldset::make('Feedback')
                        ->statePath('feedback')
                        ->columns(1)
                        ->schema([
                            $this->translatable('correct', 'When answered correctly', Textarea::make('correct')->rows(2), inline: true),
                            $this->translatable('incorrect', 'When answered incorrectly', Textarea::make('incorrect')->rows(2), inline: true),
                        ]),
                ]),
        ];
    }
}
