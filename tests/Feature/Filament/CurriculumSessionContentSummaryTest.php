<?php

use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Filament\Resources\TroveResource;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\Trove;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * The "Resource contents" panel inside a Resource block of the session Content Builder
 * (TroveItem::fields(), resources/views/filament/curriculum/trove-contents.blade.php).
 */

beforeEach(function () {
    actingAsEditor();
    Storage::fake('public');
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();
    $this->session = CurriculumSession::factory()->for($module, 'module')->create();
});

function troveWithContents(array $videoLinks = [], array $externalLinks = []): Trove
{
    return publishedTrove([
        'title' => ['en' => 'Soil health basics'],
        'video_links' => ['en' => $videoLinks],
        'external_links' => ['en' => $externalLinks],
    ]);
}

it('lists the selected trove\'s videos, links and files, badging embeddable videos', function () {
    $trove = troveWithContents(
        videoLinks: [[
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/abc123',
            'embeddable' => true,
            'title' => 'Composting walkthrough',
            'resolved_url' => 'https://www.youtube.com/watch?v=abc123',
        ]],
        externalLinks: [['link_title' => 'FAO guide to agroecology', 'link_url' => 'https://www.fao.org/agroecology']],
    );
    $trove->addMediaFromString('file body')->usingFileName('field-notes-template.pdf')->toMediaCollection('content_en');
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSee('Resource contents')
        ->assertSee('Composting walkthrough')
        ->assertSee('Embedded player')
        ->assertDontSee('Link card only')
        ->assertSee('FAO guide to agroecology')
        ->assertSee('fao.org')
        ->assertSee('field-notes-template.pdf')
        ->assertDontSeeHtml('<iframe');
});

it('badges a non-embeddable video as a link card', function () {
    $trove = troveWithContents(videoLinks: [[
        'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        'provider' => null,
        'embed_url' => null,
        'embeddable' => false,
        'title' => 'Crop rotation with legumes',
        'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
    ]]);
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSee('Crop rotation with legumes')
        ->assertSee('Link card only')
        ->assertDontSee('Embedded player');
});

it('shows an empty state and the edit link for a trove with no contents', function () {
    $trove = troveWithContents();
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSee('This resource has no videos, links or files yet.')
        ->assertSee('Edit resource')
        ->assertSeeHtml(TroveResource::getUrl('edit', ['record' => $trove]));
});

it('omits the contents section until a trove is selected', function () {
    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->fillForm([
            'content' => [
                ['type' => 'trove', 'data' => ['key' => 'k-trove', 'trove_id' => null, 'intro' => ['en' => '<p>Soon</p>']]],
            ],
        ])
        ->assertDontSee('Resource contents')
        ->assertDontSee('This resource has no videos, links or files yet.');
});

it('shows each block its own trove when several resource blocks are present', function () {
    $first = troveWithContents(externalLinks: [['link_title' => 'First guide', 'link_url' => 'https://first.example']]);
    $second = publishedTrove([
        'title' => ['en' => 'Second resource'],
        'external_links' => ['en' => [['link_title' => 'Second guide', 'link_url' => 'https://second.example']]],
    ]);
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($first)->create(['position' => 0]);
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($second)->create(['position' => 1]);

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSee('First guide')
        ->assertSee('first.example')
        ->assertSee('Second guide')
        ->assertSee('second.example');
});

it('warns about a deleted trove and shows no contents for it', function () {
    $trove = troveWithContents(externalLinks: [['link_title' => 'Gone guide', 'link_url' => 'https://gone.example']]);
    CurriculumSessionItem::factory()->for($this->session, 'session')->trove($trove)->create();
    Trove::withoutSyncingToSearch(fn () => $trove->delete());

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSee('This resource is not currently published')
        ->assertDontSee('Resource contents')
        ->assertDontSee('Gone guide');
});
