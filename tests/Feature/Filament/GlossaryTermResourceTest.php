<?php

use App\Filament\Resources\GlossaryTermResource\Pages\ListGlossaryTerms;
use App\Models\GlossaryTerm;
use Livewire\Livewire;

beforeEach(fn () => actingAsEditor());

it('creates a glossary term through the manage-records modal', function () {
    Livewire::test(ListGlossaryTerms::class)
        ->callAction('create', [
            'term' => ['en' => 'Lock-in'],
            'definition' => ['en' => 'Becoming dependent on one platform.'],
            'source' => 'Open Data Handbook',
            'source_url' => 'https://opendatahandbook.org/glossary/en/',
        ]);

    $created = GlossaryTerm::query()->get()->first(
        fn (GlossaryTerm $t) => $t->getTranslation('term', 'en') === 'Lock-in'
    );

    expect($created)->not->toBeNull()
        ->and($created->source)->toBe('Open Data Handbook')
        ->and($created->source_url)->toBe('https://opendatahandbook.org/glossary/en/');
});

it('edits a glossary term through the table modal', function () {
    $term = GlossaryTerm::factory()->create(['term' => ['en' => 'Old term']]);

    Livewire::test(ListGlossaryTerms::class)
        ->callTableAction('edit', $term, [
            'term' => ['en' => 'New term'],
            'definition' => ['en' => 'Updated definition.'],
        ]);

    expect($term->fresh()->getTranslation('term', 'en'))->toBe('New term');
});

it('deletes a glossary term through the table action', function () {
    $term = GlossaryTerm::factory()->create();

    Livewire::test(ListGlossaryTerms::class)
        ->callTableAction('delete', $term);

    expect(GlossaryTerm::query()->whereKey($term->getKey())->exists())->toBeFalse();
});
