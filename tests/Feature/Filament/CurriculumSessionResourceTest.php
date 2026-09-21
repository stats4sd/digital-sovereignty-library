<?php

use App\Filament\Resources\CurriculumSessionResource;
use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use Livewire\Livewire;

beforeEach(fn () => actingAsEditor());

it('saves translatable session content and sanitises the description', function () {
    config(['app.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $session->getKey()])
        ->fillForm([
            'title' => ['en' => 'Understanding Your Context', 'fr' => 'Comprendre votre contexte'],
            'summary' => ['en' => 'Summary', 'fr' => 'Résumé'],
            'description' => ['en' => '<p>Safe</p><script>alert(1)</script>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $session->fresh();
    expect($fresh->getTranslation('title', 'en'))->toBe('Understanding Your Context')
        ->and($fresh->getTranslation('title', 'fr'))->toBe('Comprendre votre contexte')
        ->and($fresh->getTranslation('summary', 'en'))->toBe('Summary')
        ->and($fresh->getTranslation('description', 'en'))->toBe('<p>Safe</p>')
        ->and($fresh->getTranslation('description', 'en'))->not->toContain('<script>');
});

it('does not change the slug on save', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create(['slug' => 'fixed-slug']);

    Livewire::test(EditCurriculumSession::class, ['record' => $session->getKey()])
        ->assertFormFieldIsDisabled('slug')
        ->fillForm(['title' => ['en' => 'Renamed'], 'slug' => 'new-slug'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($session->fresh()->slug)->toBe('fixed-slug');
});

it('offers the module outcomes as builds_toward options', function () {
    $module = CurriculumModule::factory()->map()->create([
        'learning_outcomes' => [
            ['key' => 'outcome-a', 'statement' => ['en' => 'First outcome'], 'in_practice' => null],
            ['key' => 'outcome-b', 'statement' => ['en' => 'Second outcome'], 'in_practice' => null],
        ],
    ]);
    $session = CurriculumSession::factory()->for($module, 'module')->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $session->getKey()])
        ->assertFormFieldExists('builds_toward')
        ->assertSee('1. First outcome')
        ->assertSee('2. Second outcome');
});

it('orders a session\'s attached troves by item position', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();
    $second = publishedTrove(['title' => ['en' => 'Second resource']]);
    $first = publishedTrove(['title' => ['en' => 'First resource']]);

    CurriculumSessionItem::factory()->for($session, 'session')->trove($second)->create(['position' => 2]);
    CurriculumSessionItem::factory()->for($session, 'session')->trove($first)->create(['position' => 1]);

    expect($session->troves()->pluck('title')->map(fn ($t) => $t['en'] ?? $t)->all())
        ->toBe(['First resource', 'Second resource']);
});

it('does not allow creating sessions directly (created via the module relation manager) and is hidden from navigation', function () {
    expect(CurriculumSessionResource::canCreate())->toBeFalse()
        ->and(CurriculumSessionResource::getPages())->not->toHaveKey('create')
        ->and(CurriculumSessionResource::shouldRegisterNavigation())->toBeFalse();
});
