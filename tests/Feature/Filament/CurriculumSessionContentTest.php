<?php

use App\Curriculum\Items\ItemRegistry;
use App\Curriculum\Items\TroveItem;
use App\Enums\CurriculumItemType;
use App\Filament\Components\ErrorBadgedTab;
use App\Filament\Resources\CurriculumSessionResource;
use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use Livewire\Livewire;

beforeEach(function () {
    actingAsEditor();
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();
    $this->session = CurriculumSession::factory()->for($module, 'module')->create([
        'summary' => ['en' => 'PARENT summary'],
    ]);
});

function contentState($component): array
{
    return array_values($component->instance()->form->getRawState()['content'] ?? []);
}

it('hydrates the content builder from item rows in position order', function () {
    $trove = publishedTrove(['title' => ['en' => 'A guide']]);
    $prose = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 1]);
    $troveItem = CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create([
        'position' => 0,
        'intro' => ['en' => '<p>Start here</p>'],
    ]);

    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()]);

    // Raw state holds TipTap documents for rich-text leaves; the dehydrated state is HTML.
    $raw = contentState($component);
    $blocks = array_values($component->instance()->form->getState()['content']);

    expect($raw)->toHaveCount(2)
        ->and($raw[0]['data']['intro']['en']['type'])->toBe('doc')
        ->and($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('trove')
        ->and($blocks[0]['data']['key'])->toBe($troveItem->key)
        ->and((int) $blocks[0]['data']['trove_id'])->toBe($trove->id)
        ->and($blocks[0]['data']['intro']['en'])->toBe('<p>Start here</p>')
        ->and($blocks[1]['type'])->toBe('prose')
        ->and($blocks[1]['data']['key'])->toBe($prose->key)
        ->and($blocks[1]['data']['body']['en'])->toBe($prose->config['body']['en']);
});

it('creates item rows in block order when the form is saved', function () {
    $trove = publishedTrove();

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'callout', 'data' => ['key' => 'k-callout', 'kind' => 'example', 'heading' => ['en' => 'Makueni'], 'body' => ['en' => '<p>Case</p>'], 'lesson' => ['en' => 'Context first']]],
                ['type' => 'trove', 'data' => ['key' => 'k-trove', 'trove_id' => $trove->id, 'intro' => ['en' => '<p>Read</p>', 'fr' => '<p>Lisez</p>']]],
                ['type' => 'note_prompt', 'data' => ['key' => 'k-prompt', 'heading' => ['en' => 'Try it'], 'prompt' => ['en' => 'Write'], 'rows' => '6']],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $items = $this->session->items()->get();

    expect($items->pluck('key')->all())->toBe(['k-callout', 'k-trove', 'k-prompt'])
        ->and($items->pluck('position')->all())->toBe([0, 1, 2])
        ->and($items[0]->type)->toBe(CurriculumItemType::Callout)
        ->and($items[0]->config['kind'])->toBe('example')
        ->and($items[0]->config['lesson'])->toBe(['en' => 'Context first'])
        ->and($items[1]->trove_id)->toBe($trove->id)
        ->and($items[1]->getTranslations('intro'))->toBe(['en' => '<p>Read</p>', 'fr' => '<p>Lisez</p>'])
        ->and($items[2]->config['rows'])->toBe(6);

    // The session's own translatables were saved by the same submit and are untouched by the blocks.
    expect($this->session->fresh()->getTranslation('summary', 'en'))->toBe('PARENT summary');
});

it('reorders rows without changing their keys', function () {
    $a = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 0]);
    $b = CurriculumSessionItem::factory()->for($this->session, 'session')->callout()->create(['position' => 1]);
    $c = CurriculumSessionItem::factory()->for($this->session, 'session')->notePrompt()->create(['position' => 2]);

    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()]);
    $uuids = array_keys($component->instance()->form->getRawState()['content']);

    $component->callFormComponentAction('content', 'reorder', arguments: ['items' => [$uuids[2], $uuids[0], $uuids[1]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->session->items()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and($a->fresh()->key)->toBe($a->key)
        ->and($b->fresh()->key)->toBe($b->key)
        ->and($c->fresh()->key)->toBe($c->key)
        ->and($this->session->items()->pluck('position')->all())->toBe([0, 1, 2]);
});

it('deletes the row of a removed block and keeps the others', function () {
    $keep = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 0]);
    $remove = CurriculumSessionItem::factory()->for($this->session, 'session')->callout()->create(['position' => 1]);

    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()]);
    $state = $component->instance()->form->getRawState()['content'];
    $removeUuid = collect($state)->search(fn ($block) => $block['data']['key'] === $remove->key);

    $component->callFormComponentAction('content', 'delete', arguments: ['item' => $removeUuid])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CurriculumSessionItem::whereKey($remove->id)->exists())->toBeFalse()
        ->and($this->session->items()->pluck('id')->all())->toBe([$keep->id]);
});

it('rejects a block missing a required field before anything is written', function (array $body) {
    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'prose', 'data' => ['key' => 'k-1', 'body' => $body]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($this->session->items()->count())->toBe(0);
})->with([
    'null locales' => [['en' => null, 'fr' => null]],
    'emptied editors (html)' => [['en' => '<p></p>', 'fr' => '<p></p>']],
    // What the browser actually submits: RichEditor raw state is a TipTap document.
    'emptied editors (tiptap docs)' => [[
        'en' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        'fr' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => []]]],
    ]],
    'rule-only document' => [[
        'en' => ['type' => 'doc', 'content' => [['type' => 'horizontalRule']]],
        'fr' => null,
    ]],
]);

it('accepts a TipTap document with text in one locale', function () {
    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'prose', 'data' => ['key' => 'k-1', 'body' => [
                    'en' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
                    'fr' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bonjour']]]]],
                ]]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->session->items()->first()->config['body'])->toBe(['fr' => '<p>Bonjour</p>']);
});

it('holds every saved item\'s resolved key in form state after saving', function () {
    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'prose', 'data' => ['body' => ['en' => '<p>No key given</p>']]],
                ['type' => 'prose', 'data' => ['key' => 'dup', 'body' => ['en' => '<p>One</p>']]],
                ['type' => 'prose', 'data' => ['key' => 'dup', 'body' => ['en' => '<p>Two</p>']]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $keysInForm = collect(contentState($component))->pluck('data.key')->all();
    $keysInDb = $this->session->items()->pluck('key')->all();

    expect($keysInForm)->toBe($keysInDb)->and(array_unique($keysInDb))->toHaveCount(3);

    // A second save is a no-op on identity.
    $component->call('save')->assertHasNoFormErrors();
    expect($this->session->items()->pluck('key')->all())->toBe($keysInDb);
});

it('accepts a rich-text field filled in only one locale', function () {
    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'prose', 'data' => ['key' => 'k-1', 'body' => ['en' => '<p></p>', 'fr' => '<p>Bonjour</p>']]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->session->items()->first()->config['body'])->toBe(['fr' => '<p>Bonjour</p>']);
});

it('offers only published canonical troves in the resource block', function () {
    $published = publishedTrove(['title' => ['en' => 'Published guide']]);
    $draft = draftTrove(['title' => ['en' => 'Draft guide']]);

    /** @var TroveItem $definition */
    $definition = app(ItemRegistry::class)->for(CurriculumItemType::Trove);

    expect($definition->options())->toHaveKey($published->id)
        ->and($definition->options())->not->toHaveKey($draft->id)
        // A selected trove that has since been unpublished stays selectable, flagged.
        ->and($definition->options($draft->id))->toHaveKey($draft->id)
        ->and($definition->options($draft->id)[$draft->id])->toContain('(unpublished)');
});

it('keeps block translatable fields from falling back to the session record', function () {
    expect(TranslatableComboField::make('summary')->fromRecord(false)->readsFromRecord())->toBeFalse()
        ->and(TranslatableComboField::make('summary')->readsFromRecord())->toBeTrue();
});

it('has no relation managers and counts content blocks in the sessions table', function () {
    CurriculumSessionItem::factory()->for($this->session, 'session')->count(3)->create();

    expect(CurriculumSessionResource::getRelations())->toBe([])
        ->and(CurriculumSession::withCount('items')->find($this->session->id)->items_count)->toBe(3);
});

it('forbids viewers from the content editor', function () {
    actingAsViewer();

    $this->get(CurriculumSessionResource::getUrl('edit', ['record' => $this->session]))->assertForbidden();
});

it('badges each tab with the number of its fields that failed validation', function () {
    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'title' => ['en' => '', 'fr' => ''],
            'content' => [
                ['type' => 'prose', 'data' => ['body' => ['en' => '', 'fr' => '']]],
                ['type' => 'prose', 'data' => ['body' => ['en' => '<p>Fine</p>', 'fr' => '']]],
                ['type' => 'prose', 'data' => ['body' => ['en' => '', 'fr' => '']]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    $badges = collect($component->instance()->form->getFlatComponents(withHidden: true))
        ->whereInstanceOf(ErrorBadgedTab::class)
        ->mapWithKeys(fn (ErrorBadgedTab $tab): array => [$tab->getLabel() => $tab->getBadge()]);

    // The title's two locale inputs collapse onto one field; two of three blocks are broken.
    expect($badges->all())->toBe(['Session' => '1', 'Content' => '2']);

    // The badge is rendered on the tab strip, so the inactive tab is flagged too.
    $component->assertSeeHtml('fi-badge');
});

it('shows no tab badges when the form is valid', function () {
    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm(['title' => ['en' => 'Valid title']])
        ->call('save')
        ->assertHasNoFormErrors();

    $badges = collect($component->instance()->form->getFlatComponents(withHidden: true))
        ->whereInstanceOf(ErrorBadgedTab::class)
        ->map(fn (ErrorBadgedTab $tab): ?string => $tab->getBadge())
        ->values();

    expect($badges->all())->toBe([null, null]);
});
