<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use Database\Seeders\Prep\CurriculumSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    bootPublicSite();
    $this->seed(CurriculumSeeder::class);
});

it('renders the curriculum page: stepper, then map, then toolkit', function () {
    $this->get('/curriculum')
        ->assertOk()
        ->assertSeeInOrder([
            'Intro',                         // stepper: back to the intro on home
            'Explore in any order',          // map
            'Tech Assessment',               // map node from DB
            'Build Your Sovereign Toolkit',  // toolkit below
        ])
        ->assertSee(route('home').'#intro') // Intro dot reopens the intro step on home
        ->assertSee('id="map"', false);      // #map anchor for deep links
});

it('renders the Farm Hack Box detail page (toolkit section) with outcomes and note', function () {
    $this->get('/toolkit/farm-hack-box')
        ->assertOk()
        ->assertSee('Get Started with the Farm Hack Box')
        ->assertSee('Learning outcomes')
        ->assertSee('entirely optional');

    // It is a toolkit module, so the map-module route does not serve it.
    $this->get('/curriculum/farm-hack-box')->assertNotFound();
});

it('renders a map module detail page with outcomes', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $module->update([
        'learning_outcomes' => [
            ['key' => (string) Str::uuid(), 'statement' => ['en' => 'Assess tools against ownership and openness'], 'in_practice' => null],
        ],
    ]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSee('Tech Assessment')
        ->assertSee('Learning Outcomes');
});

it('renders the toolkit section with all four pillars on the curriculum page', function () {
    $this->get('/curriculum')
        ->assertOk()
        ->assertSee('Build Your Sovereign Toolkit')
        ->assertSee('id="toolkit"', false)
        ->assertSee('Knowledge')
        ->assertSee('Collaboration')
        ->assertSee('Farm')
        ->assertSee('Market');
});

it('features the Farm Hack Box in the toolkit section before the pillars, not on the map', function () {
    $this->get('/curriculum')
        ->assertOk()
        // Featured card comes after the toolkit heading and before the first pillar…
        ->assertSeeInOrder(['Build Your Sovereign Toolkit', 'Get Started with the Farm Hack Box', 'Collect, store and hold onto'])
        // …and the map (which precedes the toolkit heading) does not list it.
        ->assertSeeInOrder(['Explore in any order', 'Build Your Sovereign Toolkit']);

    // Only one occurrence of the title on the page (the featured card, not a map node too).
    expect(substr_count($this->get('/curriculum')->getContent(), 'Get Started with the Farm Hack Box'))->toBe(1);
});

it('has no standalone toolkit index page', function () {
    $this->get('/toolkit')->assertNotFound();
});

it('renders a pillar detail page with its subtitle', function () {
    $this->get('/toolkit/knowledge')
        ->assertOk()
        ->assertSee('Data &amp; Knowledge Repositories', false);
});

it('404s on unknown module or pillar keys', function () {
    $this->get('/curriculum/not-a-module')->assertNotFound();
    $this->get('/toolkit/not-a-pillar')->assertNotFound();
});

it('404s a toolkit key requested as a map module (sections are distinct)', function () {
    $this->get('/curriculum/knowledge')->assertNotFound();
});

it('fails soft on the map when a coded node has no DB row', function () {
    CurriculumModule::where('key', 'tech-assessment')->delete();

    $this->get('/curriculum')->assertOk()->assertDontSee('Tech Assessment');
    $this->get('/curriculum/tech-assessment')->assertNotFound();
});

it('shows the curriculum nav item', function () {
    $this->get('/curriculum')
        ->assertSee('Curriculum');
});

// ---- Module hero, goal, outcomes, sessions ----

it('renders the module hero with number, title, CTA to the first session and goal', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $module->update([
        'number' => 2,
        'goal' => ['en' => 'Choose tools that fit your context.'],
    ]);
    $session = CurriculumSession::factory()->for($module, 'module')->create([
        'order_column' => 1,
        'title' => ['en' => 'Weighing your options'],
    ]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSee('Module 2')
        ->assertSee('Tech Assessment')
        ->assertSee('Start Session 1')
        ->assertSee('Weighing your options')
        ->assertSee('Choose tools that fit your context.')
        ->assertSee(route('curriculum.session', ['key' => 'tech-assessment', 'session' => $session->slug]));
});

it('hides the module number, goal band and outcomes band, and shows the empty session state, when absent', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $module->update(['number' => null, 'goal' => null, 'learning_outcomes' => null]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertDontSee('Start Session 1')
        ->assertDontSee('id="goal-heading"', false)
        ->assertDontSee('id="outcomes-heading"', false)
        ->assertSee('Sessions for this module are coming soon.');
});

it('shows the "In practice" note for outcomes that have one', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $module->update([
        'learning_outcomes' => [
            [
                'key' => (string) Str::uuid(),
                'statement' => ['en' => 'Assess tools against ownership and openness'],
                'in_practice' => ['en' => 'Score each tool against your criteria before you demo it.'],
            ],
        ],
    ]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSee('Assess tools against ownership and openness')
        ->assertSee('In practice:')
        ->assertSee('Score each tool against your criteria before you demo it.');
});

it('renders session cards with position, builds-toward outcome, summary and a link to the session route', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $outcomeKey = (string) Str::uuid();
    $module->update([
        'learning_outcomes' => [
            ['key' => $outcomeKey, 'statement' => ['en' => 'Assess tools against ownership and openness'], 'in_practice' => null],
        ],
    ]);
    $session = CurriculumSession::factory()->for($module, 'module')->create([
        'order_column' => 1,
        'title' => ['en' => 'Weighing your options'],
        'summary' => ['en' => 'Compare tools against your criteria.'],
        'builds_toward' => $outcomeKey,
    ]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSee('Session 1')
        ->assertSee('Builds toward LO1')
        ->assertSee('Compare tools against your criteria.')
        ->assertSee(route('curriculum.session', ['key' => 'tech-assessment', 'session' => $session->slug]));
});

it('no longer shows a trove attached directly to a map module', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $trove = publishedTrove(['title' => ['en' => 'Directly attached resource']]);
    $module->troves()->attach($trove, ['order_column' => 1]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertDontSee('Directly attached resource');
});

// ---- Session page ----

it("lists a session's attached published resources in pivot order and links to them", function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $session = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 1]);
    $second = publishedTrove(['title' => ['en' => 'Zebra resource']]);
    $first = publishedTrove(['title' => ['en' => 'Alpha resource']]);
    $session->troves()->attach($second, ['order_column' => 2]);
    $session->troves()->attach($first, ['order_column' => 1]);

    $this->get("/curriculum/tech-assessment/{$session->slug}")
        ->assertOk()
        ->assertSeeInOrder(['Alpha resource', 'Zebra resource'])
        ->assertSee(route('resources.show', $first->slug));
});

it('hides attached draft resources on the public session page', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $session = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 1]);
    $draft = draftTrove(['title' => ['en' => 'Unpublished resource']]);
    $session->troves()->attach($draft, ['order_column' => 1]);

    $this->get("/curriculum/tech-assessment/{$session->slug}")
        ->assertOk()
        ->assertDontSee('Unpublished resource');
});

it('renders the session page with the builds-toward outcome line and description', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $outcomeKey = (string) Str::uuid();
    $module->update([
        'learning_outcomes' => [
            ['key' => $outcomeKey, 'statement' => ['en' => 'Assess tools against ownership and openness'], 'in_practice' => null],
        ],
    ]);
    $session = CurriculumSession::factory()->for($module, 'module')->create([
        'order_column' => 1,
        'title' => ['en' => 'Weighing your options'],
        'description' => ['en' => '<p>Line them up against your criteria.</p>'],
        'builds_toward' => $outcomeKey,
    ]);

    $this->get("/curriculum/tech-assessment/{$session->slug}")
        ->assertOk()
        ->assertSee('Session 1')
        ->assertSee('Weighing your options')
        ->assertSee('Builds toward LO1')
        ->assertSee('Assess tools against ownership and openness')
        ->assertSee('Line them up against your criteria.', false);
});

it('shows prev/next session links in the middle and hides the missing side at each end', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $first = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 1, 'title' => ['en' => 'Weighing your options']]);
    $middle = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 2, 'title' => ['en' => 'Comparing platforms']]);
    $last = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 3, 'title' => ['en' => 'Making the call']]);

    $this->get("/curriculum/tech-assessment/{$first->slug}")
        ->assertOk()
        ->assertDontSee('aria-label="Previous session"', false)
        ->assertSee('aria-label="Next session"', false);

    $this->get("/curriculum/tech-assessment/{$middle->slug}")
        ->assertOk()
        ->assertSee('aria-label="Previous session"', false)
        ->assertSee('aria-label="Next session"', false)
        ->assertSee(route('curriculum.session', ['key' => 'tech-assessment', 'session' => $first->slug]))
        ->assertSee(route('curriculum.session', ['key' => 'tech-assessment', 'session' => $last->slug]));

    $this->get("/curriculum/tech-assessment/{$last->slug}")
        ->assertOk()
        ->assertSee('aria-label="Previous session"', false)
        ->assertDontSee('aria-label="Next session"', false);
});

it('404s the session route for an unknown slug, a slug belonging to another module, or a non-existent toolkit sub-path', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 1, 'slug' => 'weighing-your-options']);

    $otherModule = CurriculumModule::where('key', 'tech-strategy')->first();
    CurriculumSession::factory()->for($otherModule, 'module')->create(['order_column' => 1, 'slug' => 'a-session-on-another-module']);

    $this->get('/curriculum/tech-assessment/not-a-session')->assertNotFound();
    $this->get('/curriculum/tech-assessment/a-session-on-another-module')->assertNotFound();
    $this->get('/toolkit/knowledge/anything')->assertNotFound();
});
