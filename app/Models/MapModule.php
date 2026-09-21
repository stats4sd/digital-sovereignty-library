<?php

namespace App\Models;

use Database\Factories\CurriculumModuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A learning-map node (`section = map`): numbered, with a goal, learning outcomes and
 * ordered sessions. Queries through this class are scoped to map rows automatically.
 */
class MapModule extends CurriculumModule
{
    use ScopedToSection;

    protected static function newFactory(): Factory
    {
        return CurriculumModuleFactory::new()->map();
    }

    public static function section(): string
    {
        return self::SECTION_MAP;
    }
}
