<?php

namespace Database\Factories;

use App\Enums\CurriculumItemType;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\Trove;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CurriculumSessionItem>
 *
 * Each type state carries the minimal valid `config` for that type (spec §5), with
 * translatable leaves as English-only locale dictionaries.
 */
class CurriculumSessionItemFactory extends Factory
{
    protected $model = CurriculumSessionItem::class;

    public function definition(): array
    {
        return [
            'curriculum_session_id' => CurriculumSession::factory(),
            'position' => 0,
            'key' => (string) Str::uuid(),
            'type' => CurriculumItemType::Prose,
            'trove_id' => null,
            // `intro` is deliberately omitted: it is translatable, and null would be stored
            // as {"<locale>": null} rather than SQL NULL (which is what the app writes).
            'config' => [
                'body' => ['en' => '<p>'.$this->faker->paragraph().'</p>'],
            ],
        ];
    }

    public function prose(): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::Prose,
            'trove_id' => null,
            'config' => [
                'body' => ['en' => '<p>'.$this->faker->paragraph().'</p>'],
            ],
        ]);
    }

    public function callout(string $kind = 'recall'): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::Callout,
            'trove_id' => null,
            'config' => [
                'kind' => $kind,
                'body' => ['en' => '<p>'.$this->faker->paragraph().'</p>'],
                'lesson' => null,
            ],
        ]);
    }

    public function notePrompt(): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::NotePrompt,
            'trove_id' => null,
            'config' => [
                'heading' => ['en' => rtrim($this->faker->sentence(3), '.')],
                'label' => ['en' => 'Your notes'],
                'prompt' => ['en' => rtrim($this->faker->sentence(8), '.')],
                'placeholder' => ['en' => 'Write here…'],
                'rows' => 4,
            ],
        ]);
    }

    public function noteCanvas(): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::NoteCanvas,
            'trove_id' => null,
            'config' => [
                'heading' => ['en' => rtrim($this->faker->sentence(3), '.')],
                'columns' => [
                    'label' => ['en' => 'Area'],
                    'prompt' => ['en' => 'Prompt'],
                    'notes' => ['en' => 'Your notes'],
                ],
                'fields' => [
                    [
                        'id' => 'who',
                        'label' => ['en' => 'Who'],
                        'prompt' => ['en' => 'Who is involved?'],
                    ],
                    [
                        'id' => 'what',
                        'label' => ['en' => 'What'],
                        'prompt' => ['en' => 'What do they need?'],
                    ],
                ],
            ],
        ]);
    }

    public function noteMatrix(): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::NoteMatrix,
            'trove_id' => null,
            'config' => [
                'heading' => ['en' => rtrim($this->faker->sentence(3), '.')],
                'rows' => 3,
                'rowLabel' => ['en' => 'Need'],
                'fields' => [
                    [
                        'id' => 'need',
                        'label' => ['en' => 'Need'],
                        'placeholder' => ['en' => 'Describe the need'],
                        'kind' => 'text',
                        'options' => [],
                    ],
                    [
                        'id' => 'priority',
                        'label' => ['en' => 'Priority'],
                        'placeholder' => null,
                        'kind' => 'select',
                        'options' => [
                            ['id' => 'high', 'text' => ['en' => 'High']],
                            ['id' => 'low', 'text' => ['en' => 'Low']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function quiz(): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::Quiz,
            'trove_id' => null,
            'config' => [
                'heading' => ['en' => 'Check yourself'],
                'passMark' => 1,
                'items' => [
                    [
                        'id' => 'q1',
                        'kind' => 'pick-one',
                        'stem' => ['en' => rtrim($this->faker->sentence(8), '?').'?'],
                        'options' => [
                            ['id' => 'a', 'text' => ['en' => 'Option A'], 'correct' => true],
                            ['id' => 'b', 'text' => ['en' => 'Option B'], 'correct' => false],
                        ],
                        'feedback' => [
                            'correct' => ['en' => 'Right.'],
                            'incorrect' => ['en' => 'Not quite.'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function trove(Trove|int|null $trove = null): static
    {
        return $this->state(fn () => [
            'type' => CurriculumItemType::Trove,
            'trove_id' => $trove instanceof Trove ? $trove->getKey() : ($trove ?? Trove::factory()->published()),
            'config' => null,
        ]);
    }
}
