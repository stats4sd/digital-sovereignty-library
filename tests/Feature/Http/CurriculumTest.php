<?php

use App\Models\CurriculumModule;
use Database\Seeders\Prep\CurriculumSeeder;

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
    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSee('Tech Assessment')
        ->assertSee('Learning outcomes');
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

it('lists attached published resources in pivot order and links to them', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $second = publishedTrove(['title' => ['en' => 'Zebra resource']]);
    $first = publishedTrove(['title' => ['en' => 'Alpha resource']]);
    $module->troves()->attach($second, ['order_column' => 2]);
    $module->troves()->attach($first, ['order_column' => 1]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertSeeInOrder(['Alpha resource', 'Zebra resource'])
        ->assertSee(route('resources.show', $first->slug));
});

it('hides attached draft resources on the public module page', function () {
    $module = CurriculumModule::where('key', 'tech-assessment')->first();
    $draft = draftTrove(['title' => ['en' => 'Unpublished resource']]);
    $module->troves()->attach($draft, ['order_column' => 1]);

    $this->get('/curriculum/tech-assessment')
        ->assertOk()
        ->assertDontSee('Unpublished resource');
});

it('shows the curriculum nav item', function () {
    $this->get('/curriculum')
        ->assertSee('Curriculum');
});
