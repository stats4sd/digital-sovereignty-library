<?php

namespace Database\Factories;

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumSession>
 */
class CurriculumSessionFactory extends Factory
{
    protected $model = CurriculumSession::class;

    public function definition(): array
    {
        return [
            'curriculum_module_id' => CurriculumModule::factory()->map(),
            'slug' => $this->faker->unique()->slug(3),
            'order_column' => 1,
            'title' => ['en' => rtrim($this->faker->unique()->sentence(4), '.')],
            'summary' => ['en' => rtrim($this->faker->sentence(8), '.')],
            'description' => ['en' => '<p>'.$this->faker->paragraph().'</p>'],
            'builds_toward' => null,
        ];
    }
}
