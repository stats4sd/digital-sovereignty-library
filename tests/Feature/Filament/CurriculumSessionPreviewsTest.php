<?php

use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    actingAsEditor();
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);

    $module = CurriculumModule::factory()->map()->create();
    $this->session = CurriculumSession::factory()->for($module, 'module')->create();
});

it('renders every block as a preview of its state instead of an inline form', function () {
    $quiz = CurriculumSessionItem::factory()->for($this->session, 'session')->quiz()->create(['position' => 0]);
    $prose = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create(['position' => 1]);

    Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()])
        ->assertSeeHtml('fi-fo-builder-item-preview')
        ->assertSeeHtml('data-preview-type="quiz"')
        ->assertSeeHtml('data-preview-type="prose"')
        ->assertSee($quiz->config['items'][0]['stem']['en'])
        ->assertSee('✓ Option A')
        ->assertSeeHtml(strip_tags($prose->config['body']['en']) ? e(trim(strip_tags($prose->config['body']['en']))) : '');
});

it('edits a block in the modal and keeps its key across the round trip and save', function () {
    $prose = CurriculumSessionItem::factory()->for($this->session, 'session')->prose()->create();

    $component = Livewire::test(EditCurriculumSession::class, ['record' => $this->session->getKey()]);

    $state = $component->instance()->form->getRawState()['content'];
    $uuid = array_key_first($state);

    $component
        ->callAction(
            TestAction::make('edit')->schemaComponent('content')->arguments(['item' => $uuid]),
            data: [
                'key' => $prose->key,
                'heading' => ['en' => 'Changed heading'],
                'body' => ['en' => '<p>Changed body</p>'],
            ],
        )
        ->assertHasNoFormErrors();

    $after = $component->instance()->form->getRawState()['content'][$uuid]['data'];

    expect($after['key'])->toBe($prose->key)
        ->and($after['heading']['en'])->toBe('Changed heading');

    $component->call('save')->assertHasNoFormErrors();

    expect($this->session->items()->count())->toBe(1)
        ->and($prose->fresh()->config['heading']['en'])->toBe('Changed heading')
        ->and($prose->fresh()->config['body']['en'])->toBe('<p>Changed body</p>');
});
