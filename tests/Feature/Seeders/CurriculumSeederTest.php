<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\GlossaryTerm;
use Database\Seeders\Prep\CurriculumSeeder;
use Symfony\Component\Yaml\Yaml;

it('seeds the fixed module set and glossary', function () {
    $this->seed(CurriculumSeeder::class);

    $glossaryCount = count(Yaml::parseFile(database_path('curriculum/glossary.yaml'))['terms']);

    expect(CurriculumModule::count())->toBe(11)
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_MAP)->count())->toBe(5)
        // 4 pillars + the featured Farm Hack Box
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_TOOLKIT)->count())->toBe(5)
        ->and(CurriculumModule::forSection(CurriculumModule::SECTION_INTRO)->count())->toBe(1)
        ->and(GlossaryTerm::count())->toBe($glossaryCount);
});

it('merges every locale file into the seeded modules and glossary', function () {
    $this->seed(CurriculumSeeder::class);

    $locales = collect(Yaml::parseFile(database_path('curriculum/modules/intro.yaml'))['title'])
        ->keys()
        ->reject(fn (string $locale) => $locale === 'en');
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

    $glossaryCount = count(Yaml::parseFile(database_path('curriculum/glossary.yaml'))['terms']);

    expect(CurriculumModule::count())->toBe(11)
        ->and(GlossaryTerm::count())->toBe($glossaryCount)
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

it('translates the community-needs goal, structured outcomes and sessions', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->firstOrFail();

    expect($module->getTranslation('goal', 'fr', false))->not->toBe('')
        ->and($module->getTranslation('description', 'fr', false))->toContain('modules 1 et 2')
        ->and($module->learning_outcomes)->toHaveCount(4);

    foreach ($module->learning_outcomes as $outcome) {
        expect($outcome['statement']['fr'] ?? '')->not->toBe('')
            ->and($outcome['in_practice']['ar'] ?? '')->not->toBe('');
    }

    $session = $module->sessions->firstWhere('slug', 'defining-what-you-actually-need');

    expect($session->getTranslation('title', 'es', false))->toBe('Definir lo que realmente necesita')
        ->and($session->getTranslation('summary', 'zh_CN', false))->not->toBe('')
        ->and($session->getTranslation('title', 'en', false))->toBe('Defining What You Actually Need');
});

it('carries the French translation of digital-landscape outcomes', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'digital-landscape')->firstOrFail();

    expect($module->learning_outcomes)->toHaveCount(3)
        ->and($module->learning_outcomes[0]['statement']['fr'] ?? '')->toStartWith('Décrire');
});

it('seeds session items in YAML order with their keys and types', function () {
    $this->seed(CurriculumSeeder::class);

    $definition = Yaml::parseFile(database_path('curriculum/modules/community-needs.yaml'));
    $module = CurriculumModule::where('key', 'community-needs')->firstOrFail();

    $sessionsWithItems = 0;

    foreach ($definition['sessions'] as $sessionDefinition) {
        $expectedItems = $sessionDefinition['items'] ?? [];

        if ($expectedItems === []) {
            continue;
        }

        $sessionsWithItems++;
        $session = $module->sessions()->where('slug', $sessionDefinition['slug'])->firstOrFail();

        expect($session->items)->toHaveCount(count($expectedItems))
            ->and($session->items->pluck('type')->map->value->all())->toBe(array_column($expectedItems, 'type'))
            ->and($session->items->pluck('key')->all())->toBe(array_column($expectedItems, 'key'));
    }

    expect($sessionsWithItems)->toBeGreaterThan(0);
});

it('normalises seeded item config', function () {
    $this->seed(CurriculumSeeder::class);

    $definition = Yaml::parseFile(database_path('curriculum/modules/community-needs.yaml'));
    $itemDefinitions = collect($definition['sessions'])->flatMap(fn (array $session) => $session['items'] ?? []);

    $quizDefinition = $itemDefinitions->firstWhere('type', 'quiz');
    expect($quizDefinition)->not->toBeNull();

    $quiz = CurriculumSessionItem::where('key', $quizDefinition['key'])->firstOrFail();

    expect($quiz->config['passMark'])->toBeInt();

    foreach ($quiz->config['items'] as $question) {
        foreach ($question['options'] as $option) {
            expect($option['correct'])->toBeBool();
        }
    }

    $noteCanvasDefinition = $itemDefinitions->firstWhere('type', 'note_canvas');
    expect($noteCanvasDefinition)->not->toBeNull();

    $noteCanvas = CurriculumSessionItem::where('key', $noteCanvasDefinition['key'])->firstOrFail();

    expect(count($noteCanvas->config['fields']))->toBe(count($noteCanvasDefinition['config']['fields']))
        ->and($noteCanvas->config['heading']['en'] ?? '')->not->toBeEmpty();
});

it('appends a recreated item after positions the admin has renumbered', function () {
    $this->seed(CurriculumSeeder::class);

    $definition = Yaml::parseFile(database_path('curriculum/modules/community-needs.yaml'));
    $seededSession = collect($definition['sessions'])->first(fn (array $session) => count($session['items'] ?? []) > 2);
    $session = CurriculumSession::where('slug', $seededSession['slug'])->firstOrFail();

    $removed = $session->items()->orderBy('position')->skip(1)->firstOrFail();
    $removed->delete();

    $session->items()->orderBy('position')->get()
        ->each(fn (CurriculumSessionItem $item, int $index) => $item->update(['position' => $index]));

    $this->seed(CurriculumSeeder::class);

    $positions = $session->items()->orderBy('position')->pluck('position');

    expect($positions->all())->toBe(range(0, count($seededSession['items']) - 1))
        ->and($session->items()->orderBy('position')->get()->last()->key)->toBe($removed->key);
});

it('preserves an admin-edited item and adds none on re-run', function () {
    $this->seed(CurriculumSeeder::class);

    $itemCount = CurriculumSessionItem::count();
    expect($itemCount)->toBeGreaterThan(0);

    $item = CurriculumSessionItem::orderBy('id')->firstOrFail();
    $item->update(['config' => ['heading' => ['en' => 'Edited by admin'], ...collect($item->config)->except('heading')->all()]]);

    $this->seed(CurriculumSeeder::class);

    expect(CurriculumSessionItem::count())->toBe($itemCount)
        ->and($item->refresh()->config['heading']['en'] ?? null)->toBe('Edited by admin');
});
