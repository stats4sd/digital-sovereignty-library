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

    /**
     * New map modules start as drafts (hidden from learners until an editor switches the flag
     * off). Only here, not on the parent: intro and toolkit rows are always live. The column's
     * database default is false so rows that predate the flag stayed live.
     */
    protected $attributes = [
        'is_draft' => true,
    ];

    protected static function newFactory(): Factory
    {
        return CurriculumModuleFactory::new()->map();
    }

    public static function section(): string
    {
        return self::SECTION_MAP;
    }
}
