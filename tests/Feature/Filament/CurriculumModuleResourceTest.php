<?php

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Resources\CurriculumModuleResource\Pages\EditCurriculumModule;
use App\Filament\Resources\CurriculumModuleResource\Pages\ListCurriculumModules;
use App\Filament\Resources\CurriculumModuleResource\RelationManagers\SessionsRelationManager;
use App\Filament\Resources\CurriculumModuleResource\RelationManagers\TrovesRelationManager;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => actingAsEditor());

it('lists modules on the index page', function () {
    $module = CurriculumModule::factory()->map()->create(['title' => ['en' => 'Understanding the Digital Landscape']]);

    Livewire::test(ListCurriculumModules::class)
        ->assertSee('Understanding the Digital Landscape')
        ->assertSee($module->key);
});

it('does not allow creating modules (fixed set)', function () {
    expect(CurriculumModuleResource::canCreate())->toBeFalse()
        ->and(CurriculumModuleResource::getPages())->not->toHaveKey('create');
});

it('updates translatable module content and keeps per-locale JSON flat', function () {
    config(['app.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();
    $outcomeKey = (string) Str::uuid();

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->fillForm([
            'title' => ['en' => 'Tech Assessment', 'fr' => 'Évaluation technologique'],
            'learning_outcomes' => [
                [
                    'key' => $outcomeKey,
                    'statement' => ['en' => 'A', 'fr' => 'Ah'],
                    'in_practice' => ['en' => 'note'],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $module->fresh();
    expect($fresh->getTranslation('title', 'en'))->toBe('Tech Assessment')
        ->and($fresh->getTranslation('title', 'fr'))->toBe('Évaluation technologique')
        ->and($fresh->learning_outcomes[0]['key'])->toBe($outcomeKey)
        ->and($fresh->learning_outcomes[0]['statement']['en'])->toBe('A')
        ->and($fresh->learning_outcomes[0]['statement']['fr'])->toBe('Ah')
        ->and($fresh->learning_outcomes[0]['in_practice']['en'])->toBe('note');
});

it('round-trips the module number and goal', function () {
    config(['app.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->fillForm([
            'number' => 3,
            'goal' => ['en' => 'Understand your context', 'fr' => 'Comprendre votre contexte'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $module->fresh();
    expect($fresh->number)->toBe(3)
        ->and($fresh->getTranslation('goal', 'en'))->toBe('Understand your context')
        ->and($fresh->getTranslation('goal', 'fr'))->toBe('Comprendre votre contexte');
});

it('does not change the key or section on save', function () {
    $module = CurriculumModule::factory()->map()->create(['key' => 'fixed-key']);

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->fillForm(['title' => ['en' => 'Renamed']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($module->fresh()->key)->toBe('fixed-key')
        ->and($module->fresh()->section)->toBe(CurriculumModule::SECTION_MAP);
});

it('orders attached troves by the pivot order column', function () {
    $module = CurriculumModule::factory()->map()->create();
    $second = publishedTrove(['title' => ['en' => 'Second resource']]);
    $first = publishedTrove(['title' => ['en' => 'First resource']]);

    $module->troves()->attach($second, ['order_column' => 2]);
    $module->troves()->attach($first, ['order_column' => 1]);

    expect($module->troves()->pluck('title')->map(fn ($t) => $t['en'] ?? $t)->all())
        ->toBe(['First resource', 'Second resource']);
});

it('attaches published troves but rejects shadow drafts', function () {
    $module = CurriculumModule::factory()->toolkit()->create();
    $published = publishedTrove(['title' => ['en' => 'Published resource']]);
    $draft = draftTrove(['title' => ['en' => 'Draft resource']]);

    $component = Livewire::test(TrovesRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ]);

    // Published canonical troves are attachable…
    $component->callTableAction('attach', data: ['recordId' => $published->getKey()]);
    expect($module->troves()->pluck('troves.id'))->toContain($published->id);

    // …but drafts are filtered out of the options query, so attaching one fails validation.
    $component->callTableAction('attach', data: ['recordId' => $draft->getKey()])
        ->assertHasTableActionErrors(['recordId']);
    expect($module->troves()->pluck('troves.id'))->not->toContain($draft->id);
});

it('lists attached troves in the relation manager', function () {
    $module = CurriculumModule::factory()->toolkit()->create();
    $trove = publishedTrove(['title' => ['en' => 'Linked resource']]);
    $module->troves()->attach($trove, ['order_column' => 1]);

    Livewire::test(TrovesRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->assertSee('Linked resource');
});

it('hides the troves relation manager for learning-map modules and shows it for toolkit modules', function () {
    $mapModule = CurriculumModule::factory()->map()->create();
    $toolkitModule = CurriculumModule::factory()->toolkit()->create();

    expect(TrovesRelationManager::canViewForRecord($mapModule, EditCurriculumModule::class))->toBeFalse()
        ->and(TrovesRelationManager::canViewForRecord($toolkitModule, EditCurriculumModule::class))->toBeTrue();
});

it('shows the sessions relation manager only for learning-map modules', function () {
    $mapModule = CurriculumModule::factory()->map()->create();
    $toolkitModule = CurriculumModule::factory()->toolkit()->create();

    expect(SessionsRelationManager::canViewForRecord($mapModule, EditCurriculumModule::class))->toBeTrue()
        ->and(SessionsRelationManager::canViewForRecord($toolkitModule, EditCurriculumModule::class))->toBeFalse();
});

it('assigns the next order_column when a session is created via the relation manager', function () {
    $module = CurriculumModule::factory()->map()->create();

    $component = Livewire::test(SessionsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ]);

    $component->callTableAction('create', data: [
        'title' => ['en' => 'Session One'],
        'summary' => ['en' => 'First stop'],
    ])->assertHasNoTableActionErrors();

    $first = $module->sessions()->first();
    expect($first->order_column)->toBe(1);

    $component->callTableAction('create', data: [
        'title' => ['en' => 'Session Two'],
        'summary' => ['en' => 'Second stop'],
    ])->assertHasNoTableActionErrors();

    // Query without the relation's own order_column ordering so this actually inspects
    // insertion order rather than the (already-correct) order_column value.
    $second = CurriculumSession::query()->where('curriculum_module_id', $module->id)->orderByDesc('id')->first();
    expect($second->order_column)->toBe(2);
});

it('rejects a session slug already used in the same module', function () {
    $module = CurriculumModule::factory()->map()->create();
    CurriculumSession::factory()->for($module, 'module')->create(['slug' => 'session-one']);

    Livewire::test(SessionsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->callTableAction('create', data: [
        'title' => ['en' => 'Session One'],
        'slug' => 'session-one',
    ])->assertHasTableActionErrors(['slug']);
});

it('accepts a session slug already used in a different module', function () {
    $otherModule = CurriculumModule::factory()->map()->create();
    CurriculumSession::factory()->for($otherModule, 'module')->create(['slug' => 'session-one']);

    $module = CurriculumModule::factory()->map()->create();

    Livewire::test(SessionsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->callTableAction('create', data: [
        'title' => ['en' => 'Session One'],
        'slug' => 'session-one',
    ])->assertHasNoTableActionErrors();

    expect($module->sessions()->where('slug', 'session-one')->exists())->toBeTrue();
});

it('normalises a typed session slug to a dashed form', function () {
    $module = CurriculumModule::factory()->map()->create();

    Livewire::test(SessionsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->callTableAction('create', data: [
        'title' => ['en' => 'Session One'],
        'slug' => 'Part 1/2',
    ])->assertHasNoTableActionErrors();

    expect($module->sessions()->where('slug', 'part-12')->exists())->toBeTrue();
});

it('reorders sessions via the relation manager table', function () {
    $module = CurriculumModule::factory()->map()->withSessions(2)->create();
    [$first, $second] = $module->sessions()->orderBy('order_column')->get();

    Livewire::test(SessionsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($second->fresh()->order_column)->toBe(1)
        ->and($first->fresh()->order_column)->toBe(2);
});
