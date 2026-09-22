<?php

use Database\Seeders\Prep\SiteContentSeeder;

beforeEach(function () {
    bootPublicSite();
    $this->seed(SiteContentSeeder::class);
});

it('redirects the root to /home', function () {
    $this->get('/')->assertRedirect('/home');
});

it('renders the curriculum landing hero with the familiarity question', function () {
    $response = $this->get('/home');

    $response->assertOk()
        ->assertSee('Your tools. Your data.')
        ->assertSee('Start Learning')
        ->assertSee('How familiar are you with digital sovereignty?')
        // "I know the basics" lands on the curriculum page (no #map jump).
        ->assertSee(route('curriculum'))
        // The "who am I" role step was removed.
        ->assertDontSee('Community Leader');
});

it('renders the intro content below the question when the intro module is seeded', function () {
    $this->seed(\Database\Seeders\Prep\CurriculumSeeder::class);

    $this->get('/home')
        ->assertOk()
        // Question and intro content live on the same page; #intro deep links reveal it.
        ->assertSeeInOrder(['How familiar are you with digital sovereignty?', 'What is digital sovereignty?'])
        ->assertSee('id="intro-content"', false)
        ->assertSee('Continue to the learning map');
});
