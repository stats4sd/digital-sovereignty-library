# Translatable combo field: page-wide secondary-locale picker

**Date**: 2026-09-21 – 2026-09-22
**Branch**: `content-updates`
**Plan**: [docs/archive/translatable-combo-secondary-locale-picker.md](../archive/translatable-combo-secondary-locale-picker.md)

Implements the plan above. With 12 locales, every `TranslatableComboField` used to render 12 stacked inputs. Admin pages now show the primary locale (English) plus at most one other language, chosen with an "Also show" picker in the page header. Only visibility changes, and only in the browser: every locale stays in Livewire form state and is saved.

## Changes

- **Field** (`8f3cb6e`, `app/Filament/Translatable/Form/TranslatableComboField.php`)
  - Every non-primary locale input gets `->visibleJs("($store.translatableLocales?.isVisible('<code>') ?? true)")`, ANDed with any existing `visibleJs`. Hidden locales release their grid column.
  - New `->primaryLocale()` (defaults to `config('app.fallback_locale')`): the primary input has no condition and is always shown.
  - Pages without the picker have no store, so `?? true` keeps every locale visible there, as before.
- **Store + picker** (`8f3cb6e`, `resources/views/filament/translatable/secondary-locale-picker.blade.php`)
  - Registers the `translatableLocales` Alpine store on `alpine:init`, or immediately if Alpine is already running (SPA navigation).
  - Renders a select with options "English only", one per other locale, and "All languages".
  - The selection is `$persist`ed to localStorage under one global key.
- **Rollout** (`8f3cb6e` added the first page, `a42563d` the rest)
  - A `PAGE_HEADER_ACTIONS_BEFORE` render hook in `AdminPanelProvider` puts the picker on every page with translatable fields: `SiteContentPage`, Collection create/edit, CurriculumModule create/edit, `EditCurriculumSession`, Trove create/edit and `EditTagType`.
  - It is also on the list pages whose records are edited in modals: `ListGlossaryTerms`, `ListTags`, `ListTagTypes`, `ListTroveTypes`.
- **Table column headers** (`7c691d7`)
  - Inline combo fields inside table repeaters (quiz options, note-matrix columns) lose their per-locale labels.
  - `TranslatableTableColumn` adds a muted second header line ("English · Español") driven by the store, which now also carries `labels`, `primary` and `visibleLabels()`.

## Decisions (accepted as proposed)

- The default is primary-only (persisted value `''`). Editors pick a second language, or "All languages", themselves.
- The selection is global (one localStorage key), not per page or resource.
- The picker sits in the page header next to the heading.
- Repeater items (e.g. learning outcomes) inherit the gating through the nested combo field.
- No marker shows when a hidden locale has content. "Has content" badges are a possible follow-up, not built.

## Tests

- `tests/Feature/Filament/TranslatableSecondaryLocaleTest.php` covers:
  - the primary locale is ungated and the others are gated
  - a custom `primaryLocale()` and an existing `visibleJs` are preserved
  - the picker renders on an edit page and on a modal-edit list page (`ListTagTypes`), and not on a page with no translatable fields (`ListTroves`)
  - hidden locales still save
- Render-hook tests call `Filament::bootCurrentPanel()`, because `Livewire::test()` skips the `SetUpPanel` middleware that registers hooks.
- `tests/Feature/Filament/TranslatableComboFieldInlineTest.php` covers the table-header locale line.
