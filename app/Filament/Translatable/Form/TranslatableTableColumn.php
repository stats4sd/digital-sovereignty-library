<?php

namespace App\Filament\Translatable\Form;

use Filament\Forms\Components\Repeater\TableColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * A table-Repeater column header for a cell that holds an inline TranslatableComboField.
 *
 * Inside a table the per-locale inputs lose their "English" / "Español" labels (Filament hides
 * field labels in table cells once the header row is shown), so the header carries them
 * instead: a muted second line naming the locales currently on screen, in the order their
 * inputs appear. It reads the same page-wide Alpine store as the inputs' visibility, so it
 * follows the "Also show" picker live; on a page without the picker it lists every locale.
 */
class TranslatableTableColumn extends TableColumn
{
    public function getLabel(): string|Htmlable
    {
        $label = parent::getLabel();
        $label = $label instanceof Htmlable ? $label->toHtml() : e($label);

        if (parent::isMarkedAsRequired()) {
            $label .= '<sup class="fi-fo-table-repeater-header-required-mark">*</sup>';
        }

        $store = '$store.'.TranslatableComboField::LOCALE_STORE;
        $allLabels = implode(' · ', array_values($this->getLocales()));

        return new HtmlString(sprintf(
            '%s<span class="fi-translatable-table-column-locales" x-data x-text="%s">%s</span>',
            $label,
            e(sprintf("%s ? %s.visibleLabels().join(' · ') : %s", $store, $store, Js::from($allLabels))),
            e($allLabels),
        ));
    }

    /**
     * The required mark is rendered inside getLabel() so it sits beside the label text rather
     * than after the locales line, which is where the Filament view would otherwise append it.
     */
    public function isMarkedAsRequired(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function getLocales(): array
    {
        return config('branding.locales', config('app.locales', ['en' => 'English']));
    }
}
