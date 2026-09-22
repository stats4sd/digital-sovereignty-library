<?php

use App\Filament\Resources\GlossaryTermResource\Pages\ListGlossaryTerms;
use App\Models\GlossaryTerm;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => actingAsEditor());

it('creates a glossary term through the manage-records modal', function () {
    Livewire::test(ListGlossaryTerms::class)
        ->callAction('create', [
            'term' => ['en' => 'Lock-in'],
            'definition' => ['en' => 'Becoming dependent on one platform.'],
        ]);

    $created = GlossaryTerm::query()->get()->first(
        fn (GlossaryTerm $t) => $t->getTranslation('term', 'en') === 'Lock-in'
    );

    expect($created)->not->toBeNull()
        ->and($created->getTranslation('definition', 'en'))->toBe('Becoming dependent on one platform.');
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

it('opens a glossary term in a read-only view modal', function () {
    $term = GlossaryTerm::factory()->create([
        'term' => ['en' => 'Interoperability'],
        'definition' => ['en' => 'Systems that can exchange data with each other.'],
    ]);

    Livewire::test(ListGlossaryTerms::class)
        ->mountAction(TestAction::make('view')->table($term))
        ->assertActionMounted(TestAction::make('view')->table($term))
        ->assertSchemaStateSet([
            'term' => ['en' => 'Interoperability'],
            'definition' => ['en' => 'Systems that can exchange data with each other.'],
        ]);
});
