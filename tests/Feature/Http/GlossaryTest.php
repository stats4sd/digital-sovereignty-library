<?php

use App\Models\GlossaryTerm;
use Database\Seeders\Prep\CurriculumSeeder;

beforeEach(fn () => bootPublicSite());

it('embeds the glossary data and drawer on public pages', function () {
    $this->seed(CurriculumSeeder::class);

    $this->get('/curriculum')
        ->assertOk()
        ->assertSee('glossary-data')
        ->assertSee('vast volumes of structured, semistructured'); // Big data definition
});

it('omits the glossary drawer entirely when no terms exist', function () {
    $this->get('/home')->assertOk()->assertDontSee('glossary-data');
});

it('serves the current locale translation with fallback', function () {
    bootPublicSite(['en' => 'English', 'fr' => 'French']);
    // translation.target_locales is derived from branding.locales at boot (before
    // bootPublicSite runs), so the set.locale middleware needs it set explicitly
    // for ?locale=fr to be honoured.
    config(['translation.target_locales' => ['fr'], 'translation.source_locale' => 'en']);

    GlossaryTerm::factory()->create([
        'term' => ['en' => 'Data sovereignty', 'fr' => 'Souveraineté des données'],
        'definition' => ['en' => 'English definition here.', 'fr' => 'Définition en français.'],
    ]);
    GlossaryTerm::factory()->create([
        'term' => ['en' => 'Fallback-only term'],
        'definition' => ['en' => 'Falls back to English.'],
    ]);

    $this->get('/curriculum?locale=fr')
        ->assertOk()
        ->assertSee('Souveraineté des données', false)
        ->assertSee('Fallback-only term'); // spatie fallback to en
});
