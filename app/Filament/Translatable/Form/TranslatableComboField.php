<?php

namespace App\Filament\Translatable\Form;

use App\Support\HtmlSanitizer;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Concerns\CanBeCollapsed;
use Filament\Schemas\Components\Concerns\CanBeCompact;
use Filament\Schemas\Components\Concerns\HasDescription;
use Filament\Schemas\Components\Concerns\HasHeading;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Filament\Support\Concerns\HasIcon;
use Filament\Support\Concerns\HasIconColor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

//
class TranslatableComboField extends Field
{
    use CanBeCollapsed;
    use CanBeCompact;
    use HasDescription;
    use HasExtraAlpineAttributes;
    use HasHeading;
    use HasIcon;
    use HasIconColor;

    // NOTES:
    // Is a wrapper around a set of fields that all populate the same value in the database, but in different languages.

    /**
     * Name of the page-wide Alpine store that decides which secondary locale is shown.
     * Registered by the <x-filament.translatable.secondary-locale-picker> view; when a page
     * doesn't render the picker the store is absent and every locale stays visible.
     */
    public const LOCALE_STORE = 'translatableLocales';

    protected string $view = 'filament.shared.forms.translatable-combo-field';

    public Closure|array|null $locales = null;

    public Closure|string|null $primaryLocale = null;

    protected bool|Closure $readsFromRecord = true;

    protected bool|Closure $isInline = false;

    protected function setUp(): void
    {
        parent::setUp();

        // populate the inner fields with the translations from the record
        $this->formatStateUsing(function (?Model $record, $state) {

            if (! $this->readsFromRecord()) {
                return $state;
            }

            // if the record exists, and has a translation for this field, return the translations to populate the state
            if ($record && $record->{$this->getName()} && method_exists($record, 'getTranslations')) {
                return $record->getTranslations($this->getName());
            }

            return $state;
        });
    }

    /**
     * By default the combo hydrates from the form record's translations for the attribute it
     * is named after. Inside a Repeater/Builder item that is wrong: the item state is the
     * source, and a sub-field that happens to share a name with a real record attribute would
     * be silently overwritten with the parent record's value. Pass false to always use state.
     */
    public function fromRecord(bool|Closure $condition = true): static
    {
        $this->readsFromRecord = $condition;

        return $this;
    }

    public function readsFromRecord(): bool
    {
        return (bool) $this->evaluate($this->readsFromRecord);
    }

    /**
     * Render as a plain labelled field (label, then the locale inputs side by side) instead of
     * a titled Section card. Opt-in, for leaves nested inside Repeater items where the card
     * chrome adds a level of nesting the content does not need. The description, if any, is
     * shown as helper text.
     */
    public function inline(bool|Closure $condition = true): static
    {
        $this->isInline = $condition;

        return $this;
    }

    public function isInline(): bool
    {
        return (bool) $this->evaluate($this->isInline);
    }

    public function locales(Closure|array|null $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    public function getLocales(): array
    {
        // default to the app locales
        if (! $this->locales) {
            return config('app.locales');
        }

        return $this->evaluate($this->locales);
    }

    /**
     * The locale whose input is always visible (content is authored in it). Every other
     * locale's input is toggled client-side by the secondary-locale picker.
     */
    public function primaryLocale(Closure|string|null $locale): static
    {
        $this->primaryLocale = $locale;

        return $this;
    }

    public function getPrimaryLocale(): string
    {
        return $this->evaluate($this->primaryLocale) ?? config('app.fallback_locale', 'en');
    }

    public function getDescription(): string|Htmlable|null
    {
        // if no description is set, check if there is a 'hint'
        // Used so that this field can be a drop-in replacement for "Section" components.
        return $this->evaluate($this->description) ?? $this->getHint();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->evaluate($this->heading) ?? $this->getLabel();
    }

    /*
     * Set the child field. The given field will be duplicated for each locale.
     * @param Closure|string|Field $childField - either the FQDN of a Field class, or a Field instance. If a field instance is given its properties will be copied for each locale (except for name, label, and statePath)
     */
    public function childField(Closure|string|Field|null $childField = null): static
    {
        // check that $childField is a class that extends Form Field
        $childField = $this->evaluate($childField);

        if (is_string($childField) &&
            (! class_exists($childField) || ! is_subclass_of($childField, Field::class)
            )
        ) {
            abort(501, 'Invalid field type: The childField for this TranslatableComboField must be a FQDN of a class that extends Filament\Forms\Components\Field (e.g. `TextInput::class`');
        }

        $localeFields = [];

        // create a field for each locale
        foreach ($this->getLocales() as $locale => $localeLabel) {

            // clone the childField properties
            if ($childField instanceof Field) {
                $newField = clone $childField;
                // set the name and label based on the locale.
                $newField->label($localeLabel);
                $newField->statePath($locale);
            } else {
                // create a new field instance using the given FQDN
                $newField = $childField::make($locale)
                    ->label($localeLabel);
            }

            $localeFields[] = $this->applySecondaryLocaleVisibility($newField, $locale);
        }

        // check if the field is required - if yes, add requiredIf rules to ensure at least one locale is filled.
        if ($this->isRequired()) {

            $localeFields = collect($localeFields)
                ->map(fn (Field $field) => $this->makeFieldRequiredWithoutAll($field, $localeFields))
                ->toArray();
        }

        // Propagate an explicitly non-dehydrated prototype field onto the combo itself.
        // isDehydrated() returns false early for non-dehydrated fields; only a dehydrated
        // field reaches the container-dependent visibility check, which throws on a detached
        // prototype in Filament 5 — treat that (the default) as dehydrated and do nothing.
        try {
            $childIsDehydrated = ! ($childField instanceof Field) || $childField->isDehydrated();
        } catch (\Throwable) {
            $childIsDehydrated = true;
        }

        if (! $childIsDehydrated) {
            $this->dehydrated(false);
        }

        $this->childComponents($localeFields);

        return $this;
    }

    /**
     * Non-primary locale inputs are shown only when the page-wide picker selects them.
     * visibleJs() keeps the field in form state and dehydration (unlike visible()), and
     * Filament toggles `fi-hidden` on the grid wrapper so hidden locales free their column.
     */
    protected function applySecondaryLocaleVisibility(Field $field, string $locale): Field
    {
        if ($locale === $this->getPrimaryLocale()) {
            return $field;
        }

        $js = sprintf('($store.%s?.isVisible(%s) ?? true)', self::LOCALE_STORE, Js::from($locale));

        try {
            $existing = $field->getVisibleJs();
        } catch (\Throwable) {
            $existing = null;
        }

        return $field->visibleJs(filled($existing) ? "({$existing}) && {$js}" : $js);
    }

    public function required(bool|Closure $condition = true): static
    {
        // Read the raw stored child components rather than getChildComponents(), which in
        // Filament 5 resolves a Schema needing an initialized container that isn't available
        // at schema-definition time (when required() is chained on).
        $children = $this->getDefaultChildComponents();

        // if the child components exist before required() is called, apply the required rule to each child component.
        if ($condition && is_array($children) && count($children)) {

            // update child components with required rule
            $this->childComponents(
                collect($children)
                    ->map(fn (Field $field) => $this->makeFieldRequiredWithoutAll($field, $children))
                    ->toArray()
            );
        }

        return parent::required($condition);
    }

    public function makeFieldRequiredWithoutAll(Field $field, $localeFields)
    {
        $otherFields = collect($localeFields)
            ->filter(function (Field $otherField) use ($field) {
                return $otherField !== $field;
            })
            ->map(function (Field $otherField) {
                return $otherField->statePath;
            });

        if ($field instanceof RichEditor) {
            return $this->makeRichEditorRequiredWithoutAll($field, $otherFields->values()->all());
        }

        return $field
            ->requiredWithoutAll($otherFields->toArray());
    }

    /**
     * A RichEditor's raw state is a TipTap document, so an emptied editor is a non-blank
     * array and `required_without_all` would pass. Check the documents themselves: fail
     * when this locale and every sibling locale hold no content.
     *
     * @param  list<string>  $otherStatePaths
     */
    protected function makeRichEditorRequiredWithoutAll(RichEditor $field, array $otherStatePaths): RichEditor
    {
        return $field->rule(static function (RichEditor $component) use ($otherStatePaths): Closure {
            return static function (string $attribute, mixed $value, Closure $fail) use ($component, $otherStatePaths): void {
                if (! static::isEmptyRichContent($value)) {
                    return;
                }

                $siblings = $component->getContainer()->getRawState();

                foreach ($otherStatePaths as $path) {
                    if (! static::isEmptyRichContent(data_get($siblings, $path))) {
                        return;
                    }
                }

                $fail('validation.required')->translate(['attribute' => $component->getValidationAttribute()]);
            };
        });
    }

    /**
     * The single definition of "no rich-text content", for both the TipTap document held
     * in a RichEditor's raw state and the HTML it dehydrates to: empty when there is no
     * visible text. Non-text nodes alone (a rule, an empty list) do not count as content,
     * matching HtmlSanitizer::isEmpty() on the HTML side.
     */
    public static function isEmptyRichContent(mixed $value): bool
    {
        if (blank($value)) {
            return true;
        }

        if (is_string($value)) {
            return HtmlSanitizer::isEmpty($value);
        }

        if (! is_array($value)) {
            return false;
        }

        return ! static::hasTextNode($value);
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function hasTextNode(array $node): bool
    {
        if (($node['type'] ?? null) === 'text' && trim((string) ($node['text'] ?? '')) !== '') {
            return true;
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child) && static::hasTextNode($child)) {
                return true;
            }
        }

        return false;
    }
}
