<?php

namespace Database\Factories;

use App\Models\GlossaryTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlossaryTerm>
 */
class GlossaryTermFactory extends Factory
{
    protected $model = GlossaryTerm::class;

    public function definition(): array
    {
        return [
            'term' => ['en' => rtrim($this->faker->unique()->words(2, true), '.')],
            'definition' => ['en' => $this->faker->sentence(12)],
        ];
    }
}
