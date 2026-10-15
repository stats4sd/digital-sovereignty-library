<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\MapModule;
use App\Services\TrovePublisher;

beforeEach(function () {
    bootPublicSite();

    $this->module = CurriculumModule::factory()->map()->create(['key' => 'prev-mod', 'title' => ['en' => 'Preview Module']]);
    $this->one = CurriculumSession::factory()->for($this->module, 'module')->create(['order_column' => 1, 'slug' => 'one', 'title' => ['en' => 'First']]);
    $this->two = CurriculumSession::factory()->for($this->module, 'module')->draft()->create(['order_column' => 2, 'slug' => 'two', 'title' => ['en' => 'Middle draft']]);
    $this->three = CurriculumSession::factory()->for($this->module, 'module')->create(['order_column' => 3, 'slug' => 'three', 'title' => ['en' => 'Last']]);
    $this->draftModule = MapModule::factory()->draft()->create(['key' => 'draft-mod', 'title' => ['en' => 'Draft Module']]);
});

$urls = ['/preview/curriculum', '/preview/curriculum/prev-mod', '/preview/curriculum/prev-mod/one'];

it('redirects guests to the admin login', function (string $url) {
    $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
})->with($urls);

it('forbids viewers', function (string $url) {
    actingAsViewer();
    $this->get($url)->assertForbidden();
})->with($urls);

it('renders the preview with a banner and noindex for editors and admins', function (string $role, string $url) {
    $role === 'editor' ? actingAsEditor() : actingAsAdmin();

    $this->get($url)
        ->assertOk()
        ->assertSee('data-curriculum-preview', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Preview:');
})->with(['editor', 'admin'])->with($urls);

it('shows draft modules, sessions and items in the preview only', function () {
    actingAsEditor();

    $this->get('/preview/curriculum')->assertOk()->assertSee('Draft Module')->assertSee('data-draft-badge', false);
    $this->get('/preview/curriculum/draft-mod')->assertOk();
    $this->get('/preview/curriculum/prev-mod')->assertOk()->assertSee('Middle draft')->assertSee('data-draft-badge', false);
    $this->get('/preview/curriculum/prev-mod/two')->assertOk()->assertSee('Middle draft');

    CurriculumSessionItem::factory()->for($this->one, 'session')->prose()->draft()->create(['position' => 0, 'config' => ['heading' => ['en' => 'Draft heading'], 'body' => ['en' => '<p>Draft body</p>']]]);

    $this->get('/preview/curriculum/prev-mod/one')
        ->assertOk()
        ->assertSee('Draft body')
        ->assertSee('data-draft-item', false);

    // Same URLs publicly: 404 / absent.
    auth()->logout();
    usePublicContext();
    $this->get('/curriculum/draft-mod')->assertNotFound();
    $this->get('/curriculum/prev-mod/two')->assertNotFound();
    $this->get('/curriculum/prev-mod/one')->assertOk()->assertDontSee('Draft body');
});

it('keeps live session numbers identical in preview and public, with a badge for the draft', function () {
    actingAsEditor();

    $preview = $this->get('/preview/curriculum/prev-mod')->assertOk()->assertSee('Session 1')->assertSee('Session 2')->getContent();
    expect($preview)->not->toContain('Session 3');
    expect(substr_count($preview, 'data-draft-badge'))->toBe(1);

    $this->get('/preview/curriculum/prev-mod/three')->assertOk()->assertSee('Session 2');
    $this->get('/preview/curriculum/prev-mod/two')->assertOk()->assertSee('data-draft-badge', false);

    $this->get('/curriculum/prev-mod/three')->assertOk()->assertSee('Session 2');
});

it('keeps back, prev, next and map links inside the preview', function () {
    actingAsEditor();

    $page = $this->get('/preview/curriculum/prev-mod/three')->assertOk()
        ->assertSee(route('curriculum.preview.session', ['key' => 'prev-mod', 'session' => 'two']))
        ->assertSee(route('curriculum.preview.show', 'prev-mod'));
    expect($page->getContent())->not->toContain('href="'.route('curriculum.session', ['key' => 'prev-mod', 'session' => 'two']).'"');

    $this->get('/preview/curriculum/prev-mod')->assertOk()
        ->assertSee(route('curriculum.preview'))
        ->assertSee(route('curriculum.preview.session', ['key' => 'prev-mod', 'session' => 'one']))
        ->assertDontSee('href="'.route('curriculum.session', ['key' => 'prev-mod', 'session' => 'one']).'"', false);

    $this->get('/preview/curriculum')->assertOk()
        ->assertSee(route('curriculum.preview.show', 'prev-mod'))
        ->assertDontSee('href="'.route('curriculum.show', 'prev-mod').'"', false);
});

it('renders an unpublished trove item in preview, linking to the trove preview, and hides it publicly', function () {
    $trove = draftTrove(['title' => ['en' => 'Unpublished resource'], 'slug' => 'unpublished-resource']);
    CurriculumSessionItem::factory()->for($this->one, 'session')->trove($trove)->create(['position' => 0]);

    actingAsEditor();
    $this->get('/preview/curriculum/prev-mod/one')
        ->assertOk()
        ->assertSee('Unpublished resource')
        ->assertSee('/resources/preview/unpublished-resource');

    auth()->logout();
    $this->get('/curriculum/prev-mod/one')->assertOk()->assertDontSee('Unpublished resource');
});

it('shows a shadow draft title in preview and the canonical title publicly', function () {
    $trove = publishedTrove(['title' => ['en' => 'Canonical title']]);
    CurriculumSessionItem::factory()->for($this->one, 'session')->trove($trove)->create(['position' => 0]);

    $draft = app(TrovePublisher::class)->draftFor($trove);
    $draft->setTranslation('title', 'en', 'Shadow draft title')->save();

    actingAsEditor();
    $this->get('/preview/curriculum/prev-mod/one')
        ->assertOk()
        ->assertSee('Shadow draft title')
        ->assertDontSee('Canonical title');

    auth()->logout();
    $this->get('/curriculum/prev-mod/one')
        ->assertOk()
        ->assertSee('Canonical title')
        ->assertDontSee('Shadow draft title');
});

it('namespaces learner storage for preview and omits banner and noindex publicly', function () {
    actingAsEditor();
    $this->get('/preview/curriculum/prev-mod/one')->assertOk()->assertSee('dsl:v1:preview:prev-mod:one', false);

    $this->get('/curriculum/prev-mod/one')
        ->assertOk()
        ->assertDontSee('data-curriculum-preview', false)
        ->assertDontSee('noindex', false)
        ->assertDontSee('dsl:v1:preview:', false)
        ->assertSee('dsl:v1:prev-mod:one', false);
});
