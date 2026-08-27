<?php

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Resources\CurriculumModuleResource\Pages\EditCurriculumModule;
use App\Filament\Resources\CurriculumModuleResource\Pages\ListCurriculumModules;
use App\Filament\Resources\CurriculumModuleResource\RelationManagers\TrovesRelationManager;
use App\Models\CurriculumModule;
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

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->fillForm([
            'title' => ['en' => 'Tech Assessment', 'fr' => 'Évaluation technologique'],
            'learning_outcomes' => ['en' => "Outcome one\nOutcome two", 'fr' => ''],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $module->fresh();
    expect($fresh->getTranslation('title', 'en'))->toBe('Tech Assessment')
        ->and($fresh->getTranslation('title', 'fr'))->toBe('Évaluation technologique')
        ->and($fresh->getTranslation('learning_outcomes', 'en'))->toBe("Outcome one\nOutcome two");
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
    $module = CurriculumModule::factory()->map()->create();
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
    $module = CurriculumModule::factory()->map()->create();
    $trove = publishedTrove(['title' => ['en' => 'Linked resource']]);
    $module->troves()->attach($trove, ['order_column' => 1]);

    Livewire::test(TrovesRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditCurriculumModule::class,
        'activeLocale' => 'en',
    ])->assertSee('Linked resource');
});
