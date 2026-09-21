<?php

use App\Enums\CurriculumItemType;
use App\Filament\Resources\CurriculumSessionResource;
use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Filament\Resources\CurriculumSessionResource\RelationManagers\TrovesRelationManager;
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

it('attaches published troves to a session but rejects shadow drafts', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();
    $published = publishedTrove(['title' => ['en' => 'Published resource']]);
    $draft = draftTrove(['title' => ['en' => 'Draft resource']]);

    $component = Livewire::test(TrovesRelationManager::class, [
        'ownerRecord' => $session,
        'pageClass' => EditCurriculumSession::class,
        'activeLocale' => 'en',
    ]);

    $component->callTableAction('attach', data: ['recordId' => $published->getKey()]);
    expect($session->troves()->pluck('troves.id'))->toContain($published->id);

    $item = $session->items()->first();
    expect($item->type)->toBe(CurriculumItemType::Trove)
        ->and($item->trove_id)->toBe($published->id)
        ->and($item->key)->toHaveLength(36);

    $component->callTableAction('attach', data: ['recordId' => $draft->getKey()])
        ->assertHasTableActionErrors(['recordId']);
    expect($session->troves()->pluck('troves.id'))->not->toContain($draft->id);
});

it('reorders and detaches trove items through the relation manager without touching other items', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();
    $alpha = publishedTrove(['title' => ['en' => 'Alpha']]);
    $zebra = publishedTrove(['title' => ['en' => 'Zebra']]);

    $prose = CurriculumSessionItem::factory()->for($session, 'session')->prose()->create(['position' => 0]);
    $alphaItem = CurriculumSessionItem::factory()->for($session, 'session')->trove($alpha)->create(['position' => 1]);
    $zebraItem = CurriculumSessionItem::factory()->for($session, 'session')->trove($zebra)->create(['position' => 2]);

    $component = Livewire::test(TrovesRelationManager::class, [
        'ownerRecord' => $session,
        'pageClass' => EditCurriculumSession::class,
        'activeLocale' => 'en',
    ]);

    $component->call('reorderTable', [$zebra->id, $alpha->id]);

    expect($session->troves()->pluck('troves.id')->all())->toBe([$zebra->id, $alpha->id])
        ->and($alphaItem->fresh()->key)->toBe($alphaItem->key)
        ->and($zebraItem->fresh()->key)->toBe($zebraItem->key)
        ->and($prose->fresh()->position)->toBe(0);

    $component->callTableAction('detach', $zebra);

    expect(CurriculumSessionItem::whereKey($zebraItem->id)->exists())->toBeFalse()
        ->and(CurriculumSessionItem::whereKey($prose->id)->exists())->toBeTrue()
        ->and($session->items()->count())->toBe(2)
        ->and(CurriculumSession::withCount('troves')->find($session->id)->troves_count)->toBe(1);
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
