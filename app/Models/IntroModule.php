<?php

namespace App\Models;

use Database\Factories\CurriculumModuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The single onboarding intro block (`section = intro`) shown on the home page and the
 * curriculum stepper. Seeded once; never created or deleted in the admin.
 */
class IntroModule extends CurriculumModule
{
    use ScopedToSection;

    protected static function newFactory(): Factory
    {
        return CurriculumModuleFactory::new()->intro();
    }

    public static function section(): string
    {
        return self::SECTION_INTRO;
    }
}
