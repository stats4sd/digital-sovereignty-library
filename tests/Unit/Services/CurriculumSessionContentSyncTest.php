<?php

use App\Enums\CurriculumItemType;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Services\CurriculumSessionContentSync;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);
    app()->setLocale('en');
    $this->sync = app(CurriculumSessionContentSync::class);
    $this->session = CurriculumSession::factory()->create();
});

it('maps item rows to blocks in position order, flattening config and trove columns into data', function () {
    $trove = publishedTrove(['title' => ['en' => 'Guide']]);
    $prose = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 1]);
    $troveItem = CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create([
        'position' => 0,
        'intro' => ['en' => '<p>Read this</p>'],
    ]);

    $blocks = $this->sync->toBlocks($this->session);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0])->toBe([
            'type' => 'trove',
            'data' => ['key' => $troveItem->key, 'trove_id' => $trove->id, 'intro' => ['en' => '<p>Read this</p>']],
        ])
        ->and($blocks[1]['type'])->toBe('prose')
        ->and($blocks[1]['data']['key'])->toBe($prose->key)
        ->and($blocks[1]['data']['body'])->toBe($prose->config['body']);
});

it('creates rows in block order, generating keys when a block has none', function () {
    $trove = publishedTrove();

    $this->sync->apply($this->session, [
        ['type' => 'callout', 'data' => ['key' => 'fixed-key', 'kind' => 'recall', 'body' => ['en' => '<p>Recall</p>', 'fr' => null]]],
        ['type' => 'trove', 'data' => ['trove_id' => $trove->id, 'intro' => ['en' => '<p>Why</p><script>x</script>', 'fr' => '']]],
        ['type' => 'prose', 'data' => ['body' => ['en' => '<p>Text</p>']]],
    ]);

    $items = $this->session->items()->get();

    expect($items)->toHaveCount(3)
        ->and($items->pluck('position')->all())->toBe([0, 1, 2])
        ->and($items[0]->key)->toBe('fixed-key')
        ->and($items[0]->type)->toBe(CurriculumItemType::Callout)
        ->and($items[0]->config)->toEqual(['kind' => 'recall', 'heading' => null, 'body' => ['en' => '<p>Recall</p>'], 'lesson' => null])
        ->and($items[1]->key)->toHaveLength(36)
        ->and($items[1]->type)->toBe(CurriculumItemType::Trove)
        ->and($items[1]->trove_id)->toBe($trove->id)
        ->and($items[1]->config)->toBeNull()
        ->and($items[1]->getTranslations('intro'))->toBe(['en' => '<p>Why</p>'])
        ->and($items[2]->type)->toBe(CurriculumItemType::Prose)
        ->and($items[2]->trove_id)->toBeNull();
});

it('updates existing rows by key, deletes absent ones and renumbers positions', function () {
    $a = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 0]);
    $b = CurriculumSessionItem::factory()->for($this->session, 'session')->callout()->create(['position' => 1]);
    $c = CurriculumSessionItem::factory()->for($this->session, 'session')->notePrompt()->create(['position' => 2]);

    // Reverse c and a, drop b, edit a's body.
    $this->sync->apply($this->session, [
        'uuid-1' => ['type' => 'note_prompt', 'data' => ['key' => $c->key, ...$c->config]],
        'uuid-2' => ['type' => 'prose', 'data' => ['key' => $a->key, 'body' => ['en' => '<p>Edited</p>']]],
    ]);

    expect(CurriculumSessionItem::whereKey($b->id)->exists())->toBeFalse()
        ->and($this->session->items()->pluck('id')->all())->toBe([$c->id, $a->id])
        ->and($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1)
        ->and($a->fresh()->config['body'])->toBe(['en' => '<p>Edited</p>'])
        ->and($this->session->items()->count())->toBe(2);
});

it('replaces intro translations rather than merging them', function () {
    $trove = publishedTrove();
    $item = CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create([
        'intro' => ['en' => '<p>English</p>', 'fr' => '<p>Français</p>'],
    ]);

    $this->sync->apply($this->session, [
        ['type' => 'trove', 'data' => ['key' => $item->key, 'trove_id' => $trove->id, 'intro' => ['en' => '<p>Only English</p>']]],
    ]);

    expect($item->fresh()->getTranslations('intro'))->toBe(['en' => '<p>Only English</p>']);
});

it('gives a duplicated key (a cloned block) a fresh key instead of failing', function () {
    $this->sync->apply($this->session, [
        ['type' => 'prose', 'data' => ['key' => 'same', 'body' => ['en' => '<p>One</p>']]],
        ['type' => 'prose', 'data' => ['key' => 'same', 'body' => ['en' => '<p>Two</p>']]],
    ]);

    $items = $this->session->items()->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->key)->toBe('same')
        ->and($items[1]->key)->not->toBe('same')
        ->and($items[1]->config['body'])->toBe(['en' => '<p>Two</p>']);
});

it('changes an item\'s type in place when the block type changes for the same key', function () {
    $item = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create();
    $trove = publishedTrove();

    $this->sync->apply($this->session, [
        ['type' => 'trove', 'data' => ['key' => $item->key, 'trove_id' => $trove->id]],
    ]);

    $fresh = $item->fresh();

    expect($fresh->type)->toBe(CurriculumItemType::Trove)
        ->and($fresh->trove_id)->toBe($trove->id)
        ->and($fresh->config)->toBeNull();
});

it('rolls back the whole apply when one block fails validation', function () {
    $existing = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 0]);

    expect(fn () => $this->sync->apply($this->session, [
        ['type' => 'callout', 'data' => ['kind' => 'recall', 'body' => ['en' => '<p>Fine</p>']]],
        ['type' => 'prose', 'data' => ['body' => ['en' => '']]],
    ]))->toThrow(ValidationException::class);

    expect($this->session->items()->pluck('id')->all())->toBe([$existing->id]);
});

it('names the offending block in validation messages and rejects unknown types', function () {
    try {
        $this->sync->apply($this->session, [
            ['type' => 'prose', 'data' => ['body' => ['en' => '<p>Fine</p>']]],
            ['type' => 'quiz', 'data' => ['heading' => ['en' => 'Q'], 'passMark' => 5, 'items' => CurriculumSessionItem::factory()->quiz()->raw()['config']['items']]],
        ]);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('content.1.data.passMark')
            ->and($exception->errors()['content.1.data.passMark'][0])->toStartWith('Block 2 (Quiz): ');
    }

    expect(fn () => $this->sync->apply($this->session, [['type' => 'carousel', 'data' => []]]))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->sync->apply($this->session, [['type' => 'prose', 'data' => ['key' => str_repeat('k', 37), 'body' => ['en' => '<p>x</p>']]]]))
        ->toThrow(ValidationException::class);

    expect($this->session->items()->count())->toBe(0);
});

it('deletes every row when given no blocks', function () {
    CurriculumSessionItem::factory()->for($this->session, 'session')->count(2)->create();

    $this->sync->apply($this->session, []);

    expect($this->session->items()->count())->toBe(0);
});
