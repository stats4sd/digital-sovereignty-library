<?php

use App\Filament\Resources\CurriculumSessionResource\Pages\EditCurriculumSession;
use App\Filament\Resources\TroveResource\Pages\EditTrove;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Filament\Translatable\Form\TranslatableTableColumn;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

beforeEach(function () {
    actingAsEditor();
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);
});

it('renders as a Section card by default and as a plain field when inline', function () {
    $field = TranslatableComboField::make('title')->childField(TextInput::class);

    expect($field->isInline())->toBeFalse()
        ->and($field->inline()->isInline())->toBeTrue()
        ->and($field->inline(false)->isInline())->toBeFalse();
});

it('renders quiz options as a table repeater with inline locale inputs that still follow the locale picker', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();
    CurriculumSessionItem::factory()->for($session, 'session')->quiz()->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $session->getKey()])
        ->assertSeeHtml('fi-fo-table-repeater')
        ->assertSeeHtml('fi-translatable-combo-inline')
        ->assertSeeHtml("isVisible('fr')");
});

it('leaves block-level and Trove combo fields as Section cards', function () {
    $trove = publishedTrove();

    Livewire::test(EditTrove::class, ['record' => $trove->getKey()])
        ->assertSeeHtml('fi-section')
        ->assertDontSeeHtml('fi-translatable-combo-inline');
});

it('names the visible locales in a translatable table column header', function () {
    $column = TranslatableTableColumn::make('Text')->markAsRequired();
    $html = $column->getLabel()->toHtml();

    expect($column->isMarkedAsRequired())->toBeFalse() // the mark is rendered inside the label instead
        ->and($html)->toStartWith('Text<sup class="fi-fo-table-repeater-header-required-mark">*</sup>')
        ->and($html)->toContain('fi-translatable-table-column-locales')
        ->and($html)->toContain('visibleLabels()')
        ->and($html)->toContain('English · French</span>');
});

it('uses translatable table columns for quiz option text', function () {
    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();
    CurriculumSessionItem::factory()->for($session, 'session')->quiz()->create();

    Livewire::test(EditCurriculumSession::class, ['record' => $session->getKey()])
        ->assertSeeHtml('fi-translatable-table-column-locales')
        ->assertSeeHtml('visibleLabels()');
});
