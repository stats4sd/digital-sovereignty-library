<?php

namespace Database\Factories;

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;

/**
 * @extends Factory<CurriculumModule>
 */
class CurriculumModuleFactory extends Factory
{
    protected $model = CurriculumModule::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'section' => CurriculumModule::SECTION_MAP,
            'title' => ['en' => rtrim($this->faker->unique()->sentence(4), '.')],
            'description' => ['en' => '<p>'.$this->faker->paragraph().'</p>'],
            'learning_outcomes' => collect(range(1, 3))->map(fn () => [
                'key' => (string) Str::uuid(),
                'statement' => ['en' => rtrim($this->faker->sentence(6), '.')],
                'in_practice' => null,
            ])->all(),
        ];
    }

    public function map(): static
    {
        return $this->state(fn () => [
            'section' => CurriculumModule::SECTION_MAP,
            'number' => $this->faker->numberBetween(1, 5),
            'goal' => ['en' => rtrim($this->faker->sentence(8), '.')],
        ]);
    }

    public function toolkit(): static
    {
        return $this->state(fn () => [
            'section' => CurriculumModule::SECTION_TOOLKIT,
            'subtitle' => ['en' => rtrim($this->faker->sentence(3), '.')],
            'learning_outcomes' => null,
        ]);
    }

    public function intro(): static
    {
        return $this->state(fn () => [
            'section' => CurriculumModule::SECTION_INTRO,
            'learning_outcomes' => null,
        ]);
    }

    public function withSessions(int $count = 2): static
    {
        return $this->afterCreating(function (CurriculumModule $module) use ($count) {
            CurriculumSession::factory()
                ->count($count)
                ->sequence(fn (Sequence $sequence) => [
                    'order_column' => $sequence->index + 1,
                ])
                ->for($module, 'module')
                ->create();
        });
    }
}
