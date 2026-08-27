<?php

use App\Models\CurriculumModule;
use App\Models\GlossaryTerm;
use Database\Seeders\Prep\CurriculumSeeder;

it('seeds the fixed module set and glossary', function () {
    $this->seed(CurriculumSeeder::class);

    expect(CurriculumModule::count())->toBe(11)
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_MAP)->count())->toBe(5)
        // 4 pillars + the featured Farm Hack Box
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_TOOLKIT)->count())->toBe(5)
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_INTRO)->count())->toBe(1)
        ->and(GlossaryTerm::count())->toBe(21)
        // Every seeded term is credited to the base ("mother") glossary.
        ->and(GlossaryTerm::where('source', CurriculumSeeder::BASE_GLOSSARY_SOURCE)->count())->toBe(21);
});

it('is idempotent and preserves admin edits on re-run', function () {
    $this->seed(CurriculumSeeder::class);

    // Simulate an admin edit; a re-run must not clobber it or duplicate rows.
    CurriculumModule::where('key', 'tech-assessment')->first()
        ->update(['title' => ['en' => 'Edited by admin']]);

    $this->seed(CurriculumSeeder::class);

    expect(CurriculumModule::count())->toBe(11)
        ->and(GlossaryTerm::count())->toBe(21)
        ->and(CurriculumModule::where('key', 'tech-assessment')->first()->getTranslation('title', 'en'))
        ->toBe('Edited by admin');
});
