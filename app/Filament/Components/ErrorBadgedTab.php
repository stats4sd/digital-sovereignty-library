<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Str;

/**
 * A Tabs tab that flags validation errors on fields it contains.
 *
 * Filament renders a form's validation messages under each field, so an error on a field
 * inside an inactive tab is invisible until the user happens to switch to it: the save
 * button does nothing and nothing on screen says why. This tab adds a danger badge with
 * the number of fields in error, re-evaluated on every render, so after a failed save the
 * offending tab is marked wherever the user is looking.
 *
 * How it works: after validation fails, Livewire's error bag is keyed by the fields' state
 * paths (e.g. `data.title.en`, `data.content.2.data.body.en`). The tab collects the state
 * paths of every field in its child schema, at any depth (Builder/Repeater items and
 * TranslatableComboField locale children included, since they are hydrated on the same
 * request). Each error key is then attributed to the deepest field whose path covers it,
 * and that field is collapsed onto its parent for as long as the parent is itself a field
 * (so a TranslatableComboField's twelve locale inputs count as one field, while Builder
 * blocks stay separate because `content.0.data` is not a field). The badge is the number
 * of distinct fields left. No Filament internals are overridden; this is only a default
 * `badge()`/`badgeColor()`/`badgeTooltip()`, and callers may still override any of them.
 *
 * Drop-in replacement for `Filament\Schemas\Components\Tabs\Tab`. Single file, no
 * dependencies beyond Filament's own Tab, so it can be copied between projects.
 */
class ErrorBadgedTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->badge(function (ErrorBadgedTab $component): ?string {
            $count = $component->getErrorCount();

            return $count > 0 ? (string) $count : null;
        });

        $this->badgeColor('danger');

        $this->badgeTooltip(function (ErrorBadgedTab $component): ?string {
            $count = $component->getErrorCount();

            return $count > 0 ? trans_choice('{1} :count field needs attention|[2,*] :count fields need attention', $count) : null;
        });
    }

    public function getErrorCount(): int
    {
        $errors = $this->getLivewire()->getErrorBag();

        if ($errors->isEmpty()) {
            return 0;
        }

        $paths = collect($this->getChildSchema()?->getFlatFields(withHidden: true) ?? [])
            ->map(fn (Field $field): ?string => $field->getStatePath())
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $path): int => strlen($path))
            ->values();

        $fieldsInError = [];

        foreach ($errors->keys() as $key) {
            $path = $paths->first(fn (string $path): bool => $key === $path || Str::startsWith($key, "{$path}."));

            if ($path === null) {
                continue;
            }

            while (($parent = Str::beforeLast($path, '.')) !== $path && $paths->contains($parent)) {
                $path = $parent;
            }

            $fieldsInError[$path] = true;
        }

        return count($fieldsInError);
    }
}
