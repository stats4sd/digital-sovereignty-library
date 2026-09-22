<?php

namespace App\Models;

use Database\Factories\CurriculumModuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A toolkit pillar or the featured Farm Hack Box (`section = toolkit`): a subtitle plus
 * attached tool Troves. Queries through this class are scoped to toolkit rows automatically.
 */
class ToolkitModule extends CurriculumModule
{
    use ScopedToSection;

    protected static function newFactory(): Factory
    {
        return CurriculumModuleFactory::new()->toolkit();
    }

    public static function section(): string
    {
        return self::SECTION_TOOLKIT;
    }
}
