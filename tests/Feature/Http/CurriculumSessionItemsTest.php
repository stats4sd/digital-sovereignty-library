<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use Illuminate\Support\Str;

beforeEach(function () {
    bootPublicSite();

    $this->module = CurriculumModule::factory()->map()->create(['key' => 'items-module']);
    $this->session = CurriculumSession::factory()->for($this->module, 'module')->create(['order_column' => 1]);
});

function sessionItemsUrl(): string
{
    return '/curriculum/items-module/'.test()->session->slug;
}

it('renders every item type heading in position order', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');

    $factory->prose()->create(['position' => 0, 'config' => ['heading' => ['en' => 'Prose heading'], 'body' => ['en' => '<p>Prose body</p>']]]);
    $factory->callout('takeaways')->create(['position' => 1, 'config' => ['kind' => 'takeaways', 'heading' => ['en' => 'Callout heading'], 'body' => ['en' => '<p>Callout body</p>'], 'lesson' => ['en' => 'The lesson']]]);
    $factory->notePrompt()->create(['position' => 2, 'config' => ['heading' => ['en' => 'Prompt heading'], 'label' => ['en' => 'Your statement'], 'prompt' => ['en' => 'Write it'], 'placeholder' => null, 'rows' => 6]]);
    $factory->noteCanvas()->create(['position' => 3, 'config' => ['heading' => ['en' => 'Canvas heading'], 'columns' => null, 'fields' => [['id' => 'who', 'label' => ['en' => 'Who'], 'prompt' => ['en' => 'Who is involved?']]]]]);
    $factory->noteMatrix()->create(['position' => 4, 'config' => ['heading' => ['en' => 'Matrix heading'], 'rows' => 2, 'rowLabel' => ['en' => 'Challenge'], 'fields' => [['id' => 'need', 'label' => ['en' => 'Need'], 'placeholder' => null, 'kind' => 'text', 'options' => []]]]]);
    $factory->quiz()->create(['position' => 5, 'config' => ['heading' => ['en' => 'Quiz heading'], 'passMark' => 1, 'items' => [['id' => 'q1', 'kind' => 'pick-one', 'stem' => ['en' => 'Which one?'], 'options' => [['id' => 'a', 'text' => ['en' => 'Option A'], 'correct' => true], ['id' => 'b', 'text' => ['en' => 'Option B'], 'correct' => false]], 'feedback' => null]]]]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'Prose heading', 'Prose body',
            'Key takeaways', 'Callout heading', 'Callout body', 'The lesson',
            'Prompt heading', 'Your statement', 'Write it',
            'Canvas heading', 'Who is involved?',
            'Matrix heading', 'Challenge 1', 'Challenge 2',
            'Quiz heading', 'Which one?', 'Option A', 'Option B',
        ])
        ->assertSee('data-item-type="prose"', false)
        ->assertSee('data-item-type="quiz"', false);
});

it('renders items in position order regardless of insertion order', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 2, 'config' => ['heading' => null, 'body' => ['en' => '<p>Second body</p>']]]);
    $factory->prose()->create(['position' => 1, 'config' => ['heading' => null, 'body' => ['en' => '<p>First body</p>']]]);

    $this->get(sessionItemsUrl())->assertSeeInOrder(['First body', 'Second body']);
});

it('shows a trove item as a card with the title, intro and inline content', function () {
    $trove = publishedTrove([
        'title' => ['en' => 'Attached resource'],
        'video_links' => ['en' => [['url' => 'https://www.youtube.com/watch?v=abc123', 'embeddable' => true, 'embed_url' => 'https://www.youtube.com/embed/abc123']]],
        'external_links' => ['en' => [['link_title' => 'Companion guide', 'link_url' => 'https://example.org/guide']]],
    ]);
    $item = CurriculumSessionItem::factory()->for(test()->session, 'session')->trove($trove)->create(['position' => 0]);
    $item->setTranslation('intro', 'en', '<p>Read this before the video.</p>')->save();

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSeeInOrder(['Attached resource', 'Read this before the video.', 'https://www.youtube.com/embed/abc123', 'Companion guide'], false)
        ->assertSee(route('resources.show', $trove->slug))
        ->assertDontSee($trove->description);
});

it('hides a trove item whose trove is unpublished, and shows the empty state when nothing else remains', function () {
    $draft = draftTrove(['title' => ['en' => 'Unpublished resource']]);
    CurriculumSessionItem::factory()->for(test()->session, 'session')->trove($draft)->create(['position' => 0]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertDontSee('Unpublished resource')
        ->assertSee('Content for this session is coming soon.');
});

it('shows the empty state for a session with no items', function () {
    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Content for this session is coming soon.')
        ->assertDontSee('data-session-items', false);
});

it('embeds the quiz answer key as JSON and namespaces learner state by module and session', function () {
    $item = CurriculumSessionItem::factory()->for(test()->session, 'session')->quiz()->create(['position' => 0]);

    $response = $this->get(sessionItemsUrl())->assertOk();

    $response->assertSee('id="quiz-data-'.$item->key.'"', false);
    $response->assertSee('"correct":["a"]', false);
    $response->assertSee('dsl:v1:items-module:'.test()->session->slug, false);
});

it('marks prose, callout and trove intro regions as glossary scopes', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 0, 'config' => ['heading' => null, 'body' => ['en' => '<p>PROSE-BODY</p>']]]);
    $factory->callout()->create(['position' => 1, 'config' => ['kind' => 'recall', 'heading' => null, 'body' => ['en' => '<p>CALLOUT-BODY</p>'], 'lesson' => null]]);
    $troveItem = $factory->trove(publishedTrove())->create(['position' => 2]);
    $troveItem->setTranslation('intro', 'en', '<p>TROVE-INTRO</p>')->save();

    $html = $this->get(sessionItemsUrl())->assertOk()->getContent();

    foreach (['PROSE-BODY', 'CALLOUT-BODY', 'TROVE-INTRO'] as $marker) {
        expect(preg_match('/<div class="[^"]*item-body[^"]*"[^>]*data-glossary-scope>\s*<p>'.$marker.'<\/p>/', $html))->toBe(1, "{$marker} is not inside a glossary-scoped body");
    }
});

it('escapes a callout lesson as plain text', function () {
    CurriculumSessionItem::factory()->for(test()->session, 'session')->callout('example')->create([
        'position' => 0,
        'config' => ['kind' => 'example', 'heading' => null, 'body' => ['en' => '<p>Body</p>'], 'lesson' => ['en' => 'cost < benefit <b>bold</b>']],
    ]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('cost &lt; benefit &lt;b&gt;bold&lt;/b&gt;', false)
        ->assertDontSee('<b>bold</b>', false);
});

it('keeps sibling items when one trove item is hidden, and renders the learner store once', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 0, 'config' => ['heading' => null, 'body' => ['en' => '<p>Still here</p>']]]);
    $factory->trove(draftTrove(['title' => ['en' => 'Hidden draft']]))->create(['position' => 1]);
    $factory->notePrompt()->create(['position' => 2, 'config' => ['heading' => ['en' => 'Prompt'], 'label' => null, 'prompt' => ['en' => 'Write'], 'placeholder' => ['en' => 'Three sentences…'], 'rows' => 9]]);

    $response = $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Still here')
        ->assertDontSee('Hidden draft')
        ->assertSee('rows="9"', false)
        ->assertSee('placeholder="Three sentences…"', false)
        ->assertDontSee('Content for this session is coming soon.');

    expect(substr_count($response->getContent(), "Alpine.store('learner', {"))->toBe(1);
});

it('clamps the quiz pass mark to the number of renderable questions', function () {
    CurriculumSessionItem::factory()->for(test()->session, 'session')->quiz()->create([
        'position' => 0,
        'config' => ['heading' => ['en' => 'Quiz'], 'passMark' => 5, 'items' => [
            ['id' => 'q1', 'kind' => 'pick-one', 'stem' => ['en' => 'One?'], 'options' => [['id' => 'a', 'text' => ['en' => 'A'], 'correct' => true], ['id' => 'b', 'text' => ['en' => 'B'], 'correct' => false]], 'feedback' => null],
            ['id' => '', 'kind' => 'pick-one', 'stem' => ['en' => 'Broken'], 'options' => [], 'feedback' => null],
        ]],
    ]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('1 question.')
        ->assertSee('Pass mark: 1 correct answer.')
        ->assertSee('"passMark":1', false)
        ->assertDontSee('Broken');
});

it('binds note inputs to learner-store paths keyed by item key, row and field id', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $prompt = $factory->notePrompt()->create(['position' => 0]);
    $canvas = $factory->noteCanvas()->create(['position' => 1]);
    $matrix = $factory->noteMatrix()->create(['position' => 2]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee("\$store.learner.get('{$prompt->key}')", false)
        ->assertSee("\$store.learner.get('{$canvas->key}.who')", false)
        ->assertSee("\$store.learner.get('{$matrix->key}.0.need')", false)
        ->assertSee("\$store.learner.get('{$matrix->key}.2.priority')", false)
        ->assertSee('<option value="high">High</option>', false);
});

it('falls back to another locale for a config leaf missing in the current one', function () {
    bootPublicSite(['en' => 'English', 'fr' => 'Français']);

    CurriculumSessionItem::factory()->for(test()->session, 'session')->prose()->create([
        'position' => 0,
        'config' => ['heading' => ['fr' => 'Titre français'], 'body' => ['en' => '<p>English only</p>']],
    ]);

    $this->get(sessionItemsUrl().'?locale=fr')
        ->assertOk()
        ->assertSee('Titre français')
        ->assertSee('English only');
});

it('falls back to another locale for inline trove content the current locale lacks', function () {
    $trove = publishedTrove([
        'title' => ['en' => 'English-only video'],
        'video_links' => ['en' => [['url' => 'https://www.youtube.com/watch?v=xyz', 'embeddable' => true, 'embed_url' => 'https://www.youtube.com/embed/xyz']]],
    ]);
    CurriculumSessionItem::factory()->for(test()->session, 'session')->trove($trove)->create(['position' => 0]);

    $this->get(sessionItemsUrl().'?locale=fr')
        ->assertOk()
        ->assertSee('https://www.youtube.com/embed/xyz', false);
});

it('renders a contents outline listing the overview and every item with a heading, in order', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 0, 'config' => ['heading' => ['en' => 'First heading'], 'body' => ['en' => '<p>Body</p>']]]);
    $factory->prose()->create(['position' => 1, 'config' => ['heading' => null, 'body' => ['en' => '<p>Unheaded aside</p>']]]);
    $factory->quiz()->create(['position' => 2, 'config' => ['heading' => ['en' => 'Quiz heading'], 'passMark' => 1, 'items' => [['id' => 'q1', 'kind' => 'pick-one', 'stem' => ['en' => 'Which one?'], 'options' => [['id' => 'a', 'text' => ['en' => 'A'], 'correct' => true]], 'feedback' => null]]]]);
    $trove = publishedTrove(['title' => ['en' => 'Outlined resource']]);
    $factory->trove($trove)->create(['position' => 3]);

    $response = $this->get(sessionItemsUrl())->assertOk();

    $response->assertSee('data-session-outline="sidebar"', false)
        ->assertSee('data-session-outline="mobile-bar"', false)
        ->assertSee('data-session-outline="sheet"', false)
        ->assertSee('id="session-overview"', false)
        ->assertSeeInOrder(['data-session-outline="sidebar"', 'Overview', 'First heading', 'Quiz heading', 'Outlined resource', 'data-session-items'], false);

    $sidebar = Str::before(Str::after($response->getContent(), 'data-session-outline="sidebar"'), '</ol>');
    expect($sidebar)->not->toContain('Unheaded aside')
        ->and(substr_count($sidebar, 'href="#item-'))->toBe(3);
});

it('omits the contents outline when no item has a heading', function () {
    CurriculumSessionItem::factory()->for(test()->session, 'session')->prose()->create(['position' => 0, 'config' => ['heading' => null, 'body' => ['en' => '<p>Only body</p>']]]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Only body')
        ->assertDontSee('data-session-outline', false)
        ->assertDontSee('sessionOutline(', false);
});

it('drops a hidden trove item from the contents outline', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 0, 'config' => ['heading' => ['en' => 'Visible heading'], 'body' => ['en' => '<p>Body</p>']]]);
    $factory->trove(draftTrove(['title' => ['en' => 'Hidden draft resource']]))->create(['position' => 1]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Visible heading')
        ->assertDontSee('Hidden draft resource');
});

it('does not render draft items or list them in the outline', function () {
    $factory = CurriculumSessionItem::factory()->for(test()->session, 'session');
    $factory->prose()->create(['position' => 0, 'config' => ['heading' => ['en' => 'Live heading'], 'body' => ['en' => '<p>Live body</p>']]]);
    $factory->prose()->draft()->create(['position' => 1, 'config' => ['heading' => ['en' => 'Draft heading'], 'body' => ['en' => '<p>Draft body</p>']]]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Live body')
        ->assertDontSee('Draft heading')
        ->assertDontSee('Draft body')
        ->assertDontSee('data-draft-item', false);
});

it('shows the empty state when every item is a draft', function () {
    CurriculumSessionItem::factory()->for(test()->session, 'session')->prose()->draft()->create(['position' => 0, 'config' => ['heading' => ['en' => 'Draft heading'], 'body' => ['en' => '<p>Draft body</p>']]]);

    $this->get(sessionItemsUrl())
        ->assertOk()
        ->assertSee('Content for this session is coming soon.')
        ->assertDontSee('Draft body')
        ->assertDontSee('data-session-outline', false);
});
