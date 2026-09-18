<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\GlossaryTerm;
use Database\Seeders\Prep\CurriculumSeeder;
use Illuminate\Support\Str;

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

it('numbers and gives a goal to every learning-map module in map order', function () {
    $this->seed(CurriculumSeeder::class);

    $expectedNumbers = [
        'digital-landscape' => 1,
        'knowledge-justice' => 2,
        'community-needs' => 3,
        'tech-assessment' => 4,
        'tech-strategy' => 5,
    ];

    foreach ($expectedNumbers as $key => $number) {
        $module = CurriculumModule::where('key', $key)->first();

        expect($module->number)->toBe($number)
            ->and($module->getTranslation('goal', 'en'))->not->toBeEmpty();
    }
});

it('gives every learning-map module structured outcomes with uuid keys', function () {
    $this->seed(CurriculumSeeder::class);

    $mapModules = CurriculumModule::forSection(CurriculumModule::SECTION_MAP)->get();

    expect($mapModules)->toHaveCount(5);

    foreach ($mapModules as $module) {
        $outcomes = $module->learning_outcomes;

        expect($outcomes)->toBeArray()
            ->and(array_is_list($outcomes))->toBeTrue()
            ->and($outcomes)->not->toBeEmpty();

        foreach ($outcomes as $outcome) {
            expect($outcome['key'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
                ->and($outcome['statement']['en'])->not->toBeEmpty();
        }
    }
});

it('seeds four ordered community-needs sessions linked to outcomes 1 through 4', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->first();
    $sessions = $module->sessions()->orderBy('order_column')->get();

    expect($sessions)->toHaveCount(4);

    $expectedSlugs = [
        'understanding-your-operational-context',
        'defining-what-you-actually-need',
        'deciding-what-belongs-in-a-digital-system',
        'why-data-governance-starts-with-your-needs',
    ];

    $outcomes = $module->learning_outcomes;

    foreach ($sessions as $index => $session) {
        expect($session->slug)->toBe($expectedSlugs[$index])
            ->and($session->order_column)->toBe($index + 1)
            ->and($session->builds_toward)->toBe($outcomes[$index]['key']);
    }
});

it('preserves an admin-edited session title on re-run', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->first();
    $session = $module->sessions()->where('slug', 'understanding-your-operational-context')->first();
    $session->update(['title' => ['en' => 'Edited session title']]);

    $this->seed(CurriculumSeeder::class);

    expect(CurriculumSession::count())->toBe(4)
        ->and($session->refresh()->getTranslation('title', 'en'))->toBe('Edited session title');
});

it('preserves an admin-edited outcome statement on re-run', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->first();
    $outcomes = $module->learning_outcomes;
    $outcomes[0]['statement']['en'] = 'Edited outcome statement';
    $module->update(['learning_outcomes' => $outcomes]);

    $this->seed(CurriculumSeeder::class);

    expect($module->refresh()->learning_outcomes[0]['statement']['en'])->toBe('Edited outcome statement');
});

it('preserves outcomes deliberately cleared to an empty array on re-run', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $module->update(['learning_outcomes' => []]);

    $this->seed(CurriculumSeeder::class);

    expect($module->refresh()->learning_outcomes)->toBe([]);
});

it('replaces the legacy community-needs outcomes with the new set when untouched', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->first();

    $legacyOutcomes = collect([
        'Map the stakeholders who produce, control, and profit from data in your context',
        'Distinguish between a problem, a constraint, and a genuine need',
        'Write a clear statement of need before choosing any technology',
    ])->map(fn (string $statement) => [
        'key' => (string) Str::uuid(),
        'statement' => ['en' => $statement],
        'in_practice' => null,
    ])->all();

    $module->update(['learning_outcomes' => $legacyOutcomes]);

    $this->seed(CurriculumSeeder::class);

    $refreshedOutcomes = $module->refresh()->learning_outcomes;

    expect($refreshedOutcomes)->toHaveCount(4)
        ->and($refreshedOutcomes[0]['statement']['en'])
        ->toBe('Conduct a system-level diagnostic of your organisational and operational context.');
});

it('leaves community-needs outcomes untouched when they no longer match the legacy set', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->first();

    $editedOutcomes = collect([
        'Something an admin wrote instead',
        'A second custom outcome',
        'A third custom outcome',
    ])->map(fn (string $statement) => [
        'key' => (string) Str::uuid(),
        'statement' => ['en' => $statement],
        'in_practice' => null,
    ])->all();

    $module->update(['learning_outcomes' => $editedOutcomes]);

    $this->seed(CurriculumSeeder::class);

    $refreshedOutcomes = $module->refresh()->learning_outcomes;

    expect($refreshedOutcomes)->toHaveCount(3)
        ->and($refreshedOutcomes[0]['statement']['en'])->toBe('Something an admin wrote instead');
});
