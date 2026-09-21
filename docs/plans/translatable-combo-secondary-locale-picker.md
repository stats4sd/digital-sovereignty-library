# Translatable combo field: page-wide secondary-locale picker

**Status:** In Progress

Scaffolded on `content-updates` and awaiting review. Done: field-level gating, picker view, opt-in on the Curriculum Module edit page, tests. Left: review of the UX decisions below, then rolling the opt-in out to the other edit/create pages that use `TranslatableComboField` (Trove, Collection, Tag, TagType, TroveType, GlossaryTerm, CurriculumSession, SiteContent) and a change log.

## Problem

With 12 configured locales every `TranslatableComboField` renders 12 stacked inputs (12 rich editors for `description`). Content is authored in English, so editors want English plus at most one other language on screen at a time.

## Design

Client-side only. Every locale's input stays in the Livewire form state and is dehydrated on save; only its visibility is toggled in the browser. This avoids the data-loss trap of `->visible()` (hidden Filament fields are not dehydrated) and avoids a Livewire round trip per switch.

1. **Field** ([TranslatableComboField.php](../../app/Filament/Translatable/Form/TranslatableComboField.php)). `childField()` now calls `->visibleJs("($store.translatableLocales?.isVisible('fr') ?? true)")` on every non-primary locale input. Filament renders that as `x-bind:class="{ 'fi-hidden': ... }"` on the grid-cell wrapper, so hidden locales release their column instead of leaving a gap. The primary locale (new `->primaryLocale()`, default `config('app.fallback_locale')` = `en`) gets no condition and is always shown. The `?? true` fallback means pages without the picker (so without the store) keep showing every locale, exactly as before.
2. **Store + picker** ([secondary-locale-picker.blade.php](../../resources/views/filament/translatable/secondary-locale-picker.blade.php)). One Blade view registers the `translatableLocales` Alpine store and renders an "Also show" `<select>` with `<primary> only` / one option per other locale / `All languages`. The selection is `Alpine.$persist`ed to localStorage under one global key, so it follows the editor across pages and reloads. The store is registered on `alpine:init`, or immediately if Alpine is already running (Livewire SPA navigation).
3. **Opt-in per page** ([AdminPanelProvider.php](../../app/Providers/Filament/AdminPanelProvider.php)). A `PAGE_HEADER_ACTIONS_BEFORE` render hook scoped to `EditCurriculumModule::class` puts the picker in the page header. Enabling it elsewhere is one more class in `scopes`.

## Decisions to review

- **Default is "English only"** (persisted value `''`). Existing editors used to seeing all 12 boxes will now see one until they pick a language. The alternative default is `'*'` (all languages), which keeps the old behaviour until someone opts in.
- **Selection is global**, not per page: one localStorage key. Per-page or per-resource memory would be a small change to the `.as()` key.
- **Header placement.** The picker sits where header actions go, right-aligned next to the page heading. Alternatives: a `PAGE_START` hook (full-width bar above the form), or a schema `View` component at the top of each form.
- **Repeater items** (learning outcomes) inherit the gating automatically because the combo field inside the repeater does the work; new repeater items pick up the current selection on render.
- **Hidden content is invisible.** If a hidden locale already has text there is no indicator. A follow-up could show small "has content" badges for hidden locales on each combo heading.
- **Test-harness note.** Panel render hooks are only registered in `Panel::boot()`, which the `SetUpPanel` middleware triggers; `Livewire::test()` bypasses it, so render-hook tests call `Filament::bootCurrentPanel()` first.

## Verification

- `tests/Feature/Filament/TranslatableSecondaryLocaleTest.php`: primary locale ungated, others gated; custom `primaryLocale()` and existing `visibleJs` preserved; picker renders on the opted-in page and not elsewhere; hidden locales still save.
- `php artisan test --testsuite=Unit,Feature`: green apart from the pre-existing `BrowseAllTest` fallback failure.
- Manual: open `/admin/curriculum-modules/{id}/edit`, switch the picker, confirm boxes swap without a page reload, reload and confirm the choice persists, save with a hidden locale filled and confirm it persists.
