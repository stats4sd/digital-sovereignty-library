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
        ->and(GlossaryTerm::count())->toBe(21);
});

it('merges every locale file into the seeded modules and glossary', function () {
    $this->seed(CurriculumSeeder::class);

    $locales = collect(glob(database_path('seeders/Prep/curriculum-translations/*.php')))
        ->map(fn ($file) => basename($file, '.php'));
    expect($locales)->toHaveCount(11);

    $intro = CurriculumModule::where('key', 'intro')->first();
    $farm = CurriculumModule::where('key', 'farm')->first();
    $data = GlossaryTerm::where('term->en', 'Data')->first();

    foreach ($locales as $locale) {
        expect($intro->getTranslation('title', $locale, false))->not->toBe('')
            ->and($farm->getTranslation('subtitle', $locale, false))->not->toBe('')
            ->and($data->getTranslation('term', $locale, false))->not->toBe('')
            // Paragraph break and reference URL survive translation.
            ->and($data->getTranslation('definition', $locale, false))->toContain("\n\n")
            ->and($data->getTranslation('definition', $locale, false))->toContain('https://agroecologynow.net/');
    }
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
