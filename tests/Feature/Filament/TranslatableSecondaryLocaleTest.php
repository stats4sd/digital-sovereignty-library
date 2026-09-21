<?php

use App\Filament\Resources\CurriculumModuleResource\Pages\EditCurriculumModule;
use App\Filament\Resources\TagTypeResource\Pages\EditTagType;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Models\TagType;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

beforeEach(function () {
    actingAsEditor();
    config([
        'app.locales' => ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'],
        'branding.locales' => ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'],
    ]);
});

it('leaves the primary locale always visible and gates the others on the Alpine store', function () {
    $field = TranslatableComboField::make('title')->childField(TextInput::class);

    $children = collect($field->getDefaultChildComponents())->keyBy(fn (TextInput $f) => $f->getStatePath(isAbsolute: false));

    expect($children->get('en')->getVisibleJs())->toBeNull()
        ->and($children->get('fr')->getVisibleJs())->toBe("(\$store.translatableLocales?.isVisible('fr') ?? true)")
        ->and($children->get('es')->getVisibleJs())->toBe("(\$store.translatableLocales?.isVisible('es') ?? true)");
});

it('respects a custom primary locale and preserves an existing visibleJs condition', function () {
    $field = TranslatableComboField::make('title')
        ->primaryLocale('fr')
        ->childField(TextInput::make('title')->visibleJs('someFlag'));

    $children = collect($field->getDefaultChildComponents())->keyBy(fn (TextInput $f) => $f->getStatePath(isAbsolute: false));

    expect($children->get('fr')->getVisibleJs())->toBe('someFlag')
        ->and($children->get('en')->getVisibleJs())->toBe("(someFlag) && (\$store.translatableLocales?.isVisible('en') ?? true)");
});

it('renders the secondary-locale picker and store on the curriculum module edit page', function () {
    // Panel render hooks are registered in Panel::boot(), which only the SetUpPanel HTTP
    // middleware triggers; Livewire::test() bypasses it.
    Filament::bootCurrentPanel();

    $module = CurriculumModule::factory()->map()->create();

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->assertSeeHtml('data-translatable-secondary-locale-picker')
        ->assertSeeHtml('<option value="">English only</option>')
        ->assertSeeHtml('<option value="fr">French</option>')
        ->assertSeeHtml('<option value="*">All languages</option>')
        ->assertSeeHtml("isVisible('fr')");
});

it('does not render the picker on pages that have not opted in', function () {
    Filament::bootCurrentPanel();

    $tagType = TagType::factory()->create();

    Livewire::test(EditTagType::class, ['record' => $tagType->getKey()])
        ->assertDontSeeHtml('data-translatable-secondary-locale-picker');
});

it('still saves hidden secondary locales because visibleJs does not affect dehydration', function () {
    $module = CurriculumModule::factory()->map()->create();

    Livewire::test(EditCurriculumModule::class, ['record' => $module->getKey()])
        ->fillForm(['title' => ['en' => 'Primary', 'fr' => 'Secondaire', 'es' => 'Secundario']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($module->fresh()->getTranslations('title'))->toBe(['en' => 'Primary', 'fr' => 'Secondaire', 'es' => 'Secundario']);
});
