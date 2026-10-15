<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\GlossaryTerm;
use App\Models\Trove;
use App\Support\HtmlSanitizer;
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
    $term = GlossaryTerm::query()->orderBy('id')->first();

    foreach ($locales as $locale) {
        expect($intro->getTranslation('title', $locale, false))->not->toBe('')
            ->and($farm->getTranslation('subtitle', $locale, false))->not->toBe('')
            ->and($term->getTranslation('term', $locale, false))->not->toBe('')
            ->and($term->getTranslation('definition', $locale, false))->not->toBe('');
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

    // Scoped to the module: other modules (digital-landscape) seed sessions of their own.
    expect($module->sessions()->count())->toBe(4)
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

it('carries translations for the community-needs goal, description, outcomes and sessions', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->firstOrFail();

    // Content is still being revised: assert that translations exist, never what they say.
    expect($module->getTranslation('goal', 'fr', false))->not->toBe('')
        ->and($module->getTranslation('description', 'fr', false))->not->toBe('')
        ->and($module->learning_outcomes)->toHaveCount(4);

    foreach ($module->learning_outcomes as $outcome) {
        expect($outcome['statement']['fr'] ?? '')->not->toBe('')
            ->and($outcome['in_practice']['ar'] ?? '')->not->toBe('');
    }

    $session = $module->sessions->firstWhere('slug', 'defining-what-you-actually-need');

    expect($session->getTranslation('title', 'es', false))->not->toBe('')
        ->and($session->getTranslation('summary', 'zh_CN', false))->not->toBe('')
        ->and($session->getTranslation('title', 'en', false))->not->toBe('');
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

/*
|--------------------------------------------------------------------------
| Trove items and sessions without a builds_toward (Module 1 skeleton)
|--------------------------------------------------------------------------
*/

/**
 * Every `trove` reference in a module's YAML, as the seeder reads it.
 *
 * @return list<string>
 */
function troveReferencesIn(string $moduleKey): array
{
    $definition = Yaml::parseFile(database_path("curriculum/modules/{$moduleKey}.yaml"));

    return collect($definition['sessions'] ?? [])
        ->flatMap(fn (array $session) => $session['items'] ?? [])
        ->where('type', 'trove')
        ->pluck('trove')
        ->unique()
        ->values()
        ->all();
}

it('creates digital-landscape sessions but defers their items until the referenced troves exist', function () {
    $this->seed(CurriculumSeeder::class);

    $definition = Yaml::parseFile(database_path('curriculum/modules/digital-landscape.yaml'));
    $module = CurriculumModule::where('key', 'digital-landscape')->firstOrFail();

    expect($module->sessions()->count())->toBe(count($definition['sessions']))
        ->and(troveReferencesIn('digital-landscape'))->not->toBeEmpty();

    // No troves in the database yet: every session that references one holds no items.
    foreach ($definition['sessions'] as $sessionDefinition) {
        $hasTroveItems = collect($sessionDefinition['items'] ?? [])->contains('type', 'trove');
        $session = $module->sessions()->where('slug', $sessionDefinition['slug'])->firstOrFail();

        if ($hasTroveItems) {
            expect($session->items()->count())->toBe(0);
        }
    }

    // Import the troves (titles double as slugs for slug-style references), then re-run.
    foreach (troveReferencesIn('digital-landscape') as $reference) {
        publishedTrove(['title' => ['en' => $reference]]);
    }

    $this->seed(CurriculumSeeder::class);

    foreach ($definition['sessions'] as $sessionDefinition) {
        $expectedItems = $sessionDefinition['items'] ?? [];
        $session = $module->sessions()->where('slug', $sessionDefinition['slug'])->firstOrFail();
        $items = $session->items()->orderBy('position')->get();

        expect($items->pluck('key')->all())->toBe(array_column($expectedItems, 'key'))
            ->and($items->pluck('type')->map->value->all())->toBe(array_column($expectedItems, 'type'));

        foreach ($expectedItems as $index => $itemDefinition) {
            if ($itemDefinition['type'] !== 'trove') {
                continue;
            }

            $item = $items[$index];
            $trove = Trove::withDrafts()->findOrFail($item->trove_id);

            expect($item->config)->toBeNull()
                ->and(in_array($itemDefinition['trove'], [$trove->slug, $trove->getTranslation('title', 'en')], true))->toBeTrue()
                ->and($item->getTranslation('intro', 'en', false))->toBe(
                    HtmlSanitizer::clean($itemDefinition['intro']['en'] ?? ''),
                );
        }
    }
});

it('seeds a session without a builds_toward as supporting the whole module', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'digital-landscape')->firstOrFail();
    $furtherReading = $module->sessions()->where('slug', 'further-reading')->firstOrFail();
    $first = $module->sessions()->orderBy('order_column')->firstOrFail();

    expect($furtherReading->builds_toward)->toBeNull()
        ->and($furtherReading->builds_toward_outcome)->toBeNull()
        ->and($first->builds_toward)->toBe($module->learning_outcomes[0]['key']);
});

it('matches a trove reference on slug before English title and never on a shadow draft', function () {
    $canonical = publishedTrove(['title' => ['en' => 'Digital Power: State of Power 2023']]);
    Trove::factory()->draftOf($canonical)->create(['title' => ['en' => 'Digital Power: State of Power 2023']]);

    foreach (troveReferencesIn('digital-landscape') as $reference) {
        if ($reference !== 'Digital Power: State of Power 2023') {
            publishedTrove(['title' => ['en' => $reference]]);
        }
    }

    $this->seed(CurriculumSeeder::class);

    $item = CurriculumSessionItem::where('key', 'ccd9e0bf-c78e-4d2f-b567-ebd443c56334')->firstOrFail();

    expect($item->trove_id)->toBe($canonical->id);
});

/*
|--------------------------------------------------------------------------
| Draft flag
|--------------------------------------------------------------------------
*/

/** Run the seeder's private seedModule() on an ad-hoc definition (no YAML file needed). */
function seedModuleDefinition(array $definition): void
{
    (fn () => $this->seedModule($definition))->call(app(CurriculumSeeder::class));
}

it('seeds digital-landscape sessions as drafts and community-needs sessions as live', function () {
    $this->seed(CurriculumSeeder::class);

    $landscape = CurriculumModule::where('key', 'digital-landscape')->firstOrFail();
    $community = CurriculumModule::where('key', 'community-needs')->firstOrFail();

    expect($landscape->is_draft)->toBeFalse()
        ->and($landscape->sessions()->withDrafts()->count())->toBe(5)
        ->and($landscape->sessions()->withDrafts()->where('is_draft', false)->count())->toBe(0)
        ->and($community->sessions()->withDrafts()->where('is_draft', true)->count())->toBe(0);

    // Live content is really live: the public context must still see it.
    usePublicContext();
    expect(CurriculumModule::where('key', 'community-needs')->firstOrFail()->sessions()->count())->toBe(4)
        ->and(CurriculumModule::where('key', 'digital-landscape')->firstOrFail()->sessions()->count())->toBe(0)
        ->and(CurriculumSessionItem::whereHas('session.module', fn ($q) => $q->where('key', 'community-needs'))->where('is_draft', true)->count())->toBe(0);
});

it('applies draft: true from YAML to a map module and to individual items', function () {
    $this->seed(CurriculumSeeder::class);

    seedModuleDefinition([
        'key' => 'draft-module',
        'section' => CurriculumModule::SECTION_MAP,
        'draft' => true,
        'number' => 9,
        'title' => ['en' => 'Draft module'],
        'learning_outcomes' => [['statement' => ['en' => 'Outcome one']]],
        'sessions' => [[
            'slug' => 'mixed',
            'title' => ['en' => 'Mixed'],
            'items' => [
                ['key' => 'live-item', 'type' => 'prose', 'config' => ['body' => ['en' => '<p>Live</p>']]],
                ['key' => 'draft-item', 'type' => 'prose', 'draft' => true, 'config' => ['body' => ['en' => '<p>Draft</p>']]],
            ],
        ]],
    ]);

    $module = CurriculumModule::withDrafts()->where('key', 'draft-module')->firstOrFail();
    $session = $module->sessions()->withDrafts()->firstOrFail();
    $items = $session->items()->withDrafts()->orderBy('position')->get();

    expect($module->is_draft)->toBeTrue()
        ->and($session->is_draft)->toBeFalse()
        ->and($items->pluck('is_draft', 'key')->all())->toBe(['live-item' => false, 'draft-item' => true])
        ->and($items->firstWhere('key', 'draft-item')->config)->not->toHaveKey('draft');
});

it('never changes is_draft on an existing row when re-run', function () {
    $this->seed(CurriculumSeeder::class);

    $module = CurriculumModule::where('key', 'community-needs')->firstOrFail();
    $session = $module->sessions()->orderBy('order_column')->firstOrFail();
    $item = $session->items()->orderBy('position')->firstOrFail();

    $module->update(['is_draft' => true]);
    $session->update(['is_draft' => true]);
    $item->update(['is_draft' => true]);

    $landscapeSession = CurriculumModule::where('key', 'digital-landscape')->firstOrFail()
        ->sessions()->withDrafts()->where('slug', 'further-reading')->firstOrFail();
    $landscapeSession->update(['is_draft' => false]);

    $this->seed(CurriculumSeeder::class);

    expect($module->fresh()->is_draft)->toBeTrue()
        ->and($session->fresh()->is_draft)->toBeTrue()
        ->and($item->fresh()->is_draft)->toBeTrue()
        ->and($landscapeSession->fresh()->is_draft)->toBeFalse()
        // Hidden draft rows are found, not duplicated, even outside the panel.
        ->and(CurriculumModule::withDrafts()->where('key', 'community-needs')->count())->toBe(1)
        ->and($module->sessions()->withDrafts()->where('slug', $session->slug)->count())->toBe(1)
        ->and($session->items()->withDrafts()->where('key', $item->key)->count())->toBe(1);
});

it('finds existing draft rows from a public (scoped) context instead of duplicating them', function () {
    $this->seed(CurriculumSeeder::class);
    usePublicContext();

    $this->seed(CurriculumSeeder::class);

    $landscape = CurriculumModule::where('key', 'digital-landscape')->firstOrFail();

    expect($landscape->sessions()->withDrafts()->count())->toBe(5)
        ->and(CurriculumSession::withDrafts()->where('slug', 'further-reading')->count())->toBe(1);
});

it('rejects draft: true on an intro or toolkit module', function (string $section) {
    seedModuleDefinition([
        'key' => "always-live-{$section}",
        'section' => $section,
        'draft' => true,
        'title' => ['en' => 'Always live'],
    ]);
})->with([CurriculumModule::SECTION_INTRO, CurriculumModule::SECTION_TOOLKIT])
    ->throws(InvalidArgumentException::class, 'always live');
