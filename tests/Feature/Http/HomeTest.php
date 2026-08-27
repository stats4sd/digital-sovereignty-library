<?php

use Database\Seeders\Prep\SiteContentSeeder;

beforeEach(function () {
    bootPublicSite();
    $this->seed(SiteContentSeeder::class);
});

it('redirects the root to /home', function () {
    $this->get('/')->assertRedirect('/home');
});

it('renders the curriculum landing hero with onboarding', function () {
    $response = $this->get('/home');

    $response->assertOk()
        ->assertSee('Your tools. Your data.')
        ->assertSee('Start Learning')
        ->assertSee('How familiar are you with digital sovereignty?')
        // "I know the basics" lands on the curriculum map.
        ->assertSee(route('curriculum'));
});

it('renders the standalone intro step when the intro module is seeded', function () {
    $this->seed(\Database\Seeders\Prep\CurriculumSeeder::class);

    $this->get('/home')
        ->assertOk()
        ->assertSee('What is digital sovereignty?')
        ->assertSee('Continue to the learning map');
});
