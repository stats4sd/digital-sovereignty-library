<?php

use App\Filament\Pages\SiteContentPage;
use App\Models\SiteContent;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('persists translatable content keys to SiteContent', function () {
    Livewire::test(SiteContentPage::class)
        ->fillForm([
            'library_heading_line1' => ['en' => 'Welcome'],
            'library_hero_description' => ['en' => 'Explore our resources.'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteContent::get('library_heading_line1'))->toBe('Welcome')
        ->and(SiteContent::get('library_hero_description'))->toBe('Explore our resources.');
});
