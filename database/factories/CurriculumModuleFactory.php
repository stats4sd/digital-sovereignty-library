<?php

namespace Database\Factories;

use App\Models\CurriculumModule;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'learning_outcomes' => ['en' => implode("\n", $this->faker->sentences(3))],
        ];
    }

    public function map(): static
    {
        return $this->state(fn () => ['section' => CurriculumModule::SECTION_MAP]);
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
}
