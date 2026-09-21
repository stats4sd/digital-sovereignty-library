<?php

use App\Enums\CurriculumItemType;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\Scopes\PublishedScope;
use App\Models\Trove;
use Illuminate\Database\QueryException;

beforeEach(function () {
    config(['branding.locales' => ['en' => 'English']]);
    app()->setLocale('en');
});

it('assigns a uuid key when created without one', function () {
    $item = CurriculumSessionItem::factory()->create(['key' => null]);

    expect($item->key)->toBeString()->toHaveLength(36);
});

it('keeps an explicitly supplied key', function () {
    $item = CurriculumSessionItem::factory()->create(['key' => 'fixed-key']);

    expect($item->fresh()->key)->toBe('fixed-key');
});

it('refuses to change the key of an existing item', function () {
    $item = CurriculumSessionItem::factory()->create();

    expect(fn () => $item->update(['key' => 'renamed']))
        ->toThrow(LogicException::class);

    expect($item->fresh()->key)->not->toBe('renamed');
});

it('allows other attributes to change without touching the key', function () {
    $item = CurriculumSessionItem::factory()->prose()->create();

    $item->update(['position' => 7, 'config' => ['body' => ['en' => '<p>Edited</p>']]]);

    expect($item->fresh())
        ->position->toBe(7)
        ->config->toBe(['body' => ['en' => '<p>Edited</p>']]);
});

it('casts type to the enum and config to an array, and translates intro', function () {
    $item = CurriculumSessionItem::factory()->quiz()->create([
        'intro' => ['en' => '<p>Read this first</p>', 'fr' => '<p>Lisez ceci</p>'],
    ]);

    $fresh = $item->fresh();

    expect($fresh->type)->toBe(CurriculumItemType::Quiz)
        ->and($fresh->config['passMark'])->toBe(1)
        ->and($fresh->getTranslation('intro', 'fr'))->toBe('<p>Lisez ceci</p>');
});

it('enforces a unique key per session but allows the same key on another session', function () {
    $session = CurriculumSession::factory()->create();
    CurriculumSessionItem::factory()->for($session, 'session')->create(['key' => 'shared']);

    expect(fn () => CurriculumSessionItem::factory()->for($session, 'session')->create(['key' => 'shared']))
        ->toThrow(QueryException::class);

    $other = CurriculumSessionItem::factory()->create(['key' => 'shared']);

    expect($other->exists)->toBeTrue();
});

it('orders a session\'s items by position then id', function () {
    $session = CurriculumSession::factory()->create();
    $third = CurriculumSessionItem::factory()->for($session, 'session')->create(['position' => 2]);
    $first = CurriculumSessionItem::factory()->for($session, 'session')->create(['position' => 0]);
    $second = CurriculumSessionItem::factory()->for($session, 'session')->create(['position' => 1]);

    expect($session->items()->pluck('id')->all())->toBe([$first->id, $second->id, $third->id])
        ->and($session->items->first()->session->is($session))->toBeTrue();
});

it('exposes only trove items through troves(), in position order', function () {
    $session = CurriculumSession::factory()->create();
    $zebra = publishedTrove(['title' => ['en' => 'Zebra']]);
    $alpha = publishedTrove(['title' => ['en' => 'Alpha']]);

    CurriculumSessionItem::factory()->for($session, 'session')->prose()->create(['position' => 0]);
    CurriculumSessionItem::factory()->for($session, 'session')->trove($zebra)->create(['position' => 2]);
    CurriculumSessionItem::factory()->for($session, 'session')->trove($alpha)->create(['position' => 1]);

    expect($session->items()->count())->toBe(3)
        ->and($session->troves()->pluck('troves.id')->all())->toBe([$alpha->id, $zebra->id])
        ->and($alpha->curriculumSessions()->pluck('curriculum_sessions.id')->all())->toBe([$session->id]);
});

it('resolves trove() to null publicly for an unpublished trove', function () {
    $draft = draftTrove();
    $item = CurriculumSessionItem::factory()->trove($draft)->create();

    usePublicContext();

    expect($item->fresh()->trove)->toBeNull()
        ->and($item->trove()->withoutGlobalScope(PublishedScope::class)->first()?->id)->toBe($draft->id);
});

it('nulls trove_id when the trove is deleted and cascades when the session is deleted', function () {
    $session = CurriculumSession::factory()->create();
    $trove = publishedTrove();
    $item = CurriculumSessionItem::factory()->for($session, 'session')->trove($trove)->create();

    // Trove soft-deletes; only a hard delete exercises the FK's nullOnDelete.
    Trove::withoutSyncingToSearch(fn () => $trove->forceDelete());

    expect($item->fresh()->trove_id)->toBeNull();

    $session->delete();

    expect(CurriculumSessionItem::whereKey($item->id)->exists())->toBeFalse();
});
