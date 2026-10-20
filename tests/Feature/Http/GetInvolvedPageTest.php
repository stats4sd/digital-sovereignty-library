<?php

beforeEach(function () {
    bootPublicSite();
});

it('renders the get involved page', function () {
    $this->get('/get-involved')
        ->assertOk()
        ->assertSee('Get Involved');
});

it('shows the get involved link in the nav', function () {
    $this->get('/get-involved')
        ->assertSee('href="/get-involved"', false);
});

it('lists the initiatives with their links and images', function () {
    $response = $this->get('/get-involved')->assertOk();

    foreach ([
        ['Earth Defenders Toolkit', 'https://www.earthdefenderstoolkit.com/toolkit/', 'earth_defenders.jpg'],
        ['AfriTAP', 'https://assess.technology/regional-technology-assessment-platforms/africa/', 'afritap.jpg'],
        ['Red TECLA', 'https://assess.technology/regional-technology-assessment-platforms/latin-america/', 'red_tecla.jpg'],
        ['GOAT', 'https://goatech.org/', 'goat.jpg'],
        ['GIAA', 'https://www.gia-agroecology.org/', 'giaa.jpg'],
        ['SPACAT', 'https://www.our-sci.net/support/', 'our_sci.jpg'],
        ['Guardian Connector', 'https://docs.guardianconnector.net/overview/', 'guardian_connector.jpg'],
        ['Float', 'https://float.ag/', 'float.jpg'],
    ] as [$title, $url, $image]) {
        $response->assertSee($title);
        $response->assertSee('href="'.$url.'"', false);
        $response->assertSee('images/get-involved/'.$image, false);
    }
});

it('translates the nav label, heading and descriptions', function () {
    bootPublicSite(['en' => 'English', 'es' => 'Español']);
    // translation.target_locales is derived at boot, so set it explicitly for ?locale=es.
    config(['translation.target_locales' => ['es'], 'translation.source_locale' => 'en']);

    $this->get('/get-involved?locale=es')
        ->assertOk()
        ->assertSee('Participa')
        ->assertSee('Iniciativas clave para apoyar vías digitales lideradas por la comunidad')
        ->assertSee('Float es un experimento de priorización', false)
        ->assertDontSee('Float is an experiment');
});

it('shows the link text without the scheme or trailing slash', function () {
    $this->get('/get-involved')
        ->assertSee('www.earthdefenderstoolkit.com/toolkit <', false)
        ->assertDontSee('https://float.ag/ <', false);
});
