# Curriculum sessions + module page redesign

**Date**: 2026-09-08
**Branch**: `module-pages`
**Plan**: [docs/plans/2026-09-08-curriculum-sessions-module-page.md](../plans/2026-09-08-curriculum-sessions-module-page.md)

Implements the plan above: the public curriculum module page now follows the course design prototype, learning-map modules contain ordered **sessions** that own the attached resources, and learning outcomes are structured (statement + optional "in practice" note). Toolkit pillars, farm-hack-box and the intro module keep attaching resources directly.

## Schema

- `curriculum_modules`: new `number` (tinyint, "Module 3") and `goal` (translatable JSON). `learning_outcomes` is now a non-translatable array cast: `[{key: uuid, statement: {locale: text}, in_practice: {locale: text} | null}]`.
- New `curriculum_sessions` (module FK, slug, order_column, translatable title/summary/description, `builds_toward` = the `key` of an outcome item, unique(module, slug)) and `curriculum_session_trove` pivot (ordered).
- Data migrations (DB facade only, one-way, re-run safe): `2026_09_08_100300` converts newline outcomes to the structured shape; `2026_09_08_100400` moves any map-module trove attachments into an auto-created `session-1` per module.

## Backend

- `CurriculumModule`: `sessions()` HasMany with `chaperone('module')`, `outcomes_list` accessor returns `[key, statement, in_practice]` for the current locale (legacy/malformed values yield `[]`).
- `CurriculumSession`: slug auto-generated and de-duplicated within the module, `number` = 1-based position among siblings (never the raw `order_column`), `builds_toward_outcome` resolves the outcome and its position for "LOn" labels.
- `App\Support\TranslatableText::pick()` — locale fallback for plain locale-keyed arrays (current locale → other configured locales → any filled value; non-string leaves skipped).
- `Trove::curriculumSessions()`.
- New `CurriculumModulePolicy` (no create/delete) and `CurriculumSessionPolicy` (mutations need `canEdit()`). Before this, curriculum content had no policy at all, so viewers could edit it. `GlossaryTerm` still has that gap.

## Admin (Filament)

- Module form: `number` and `goal` (map modules only); outcomes are a reorderable `Repeater` with a hidden uuid `key` and per-locale statement / in-practice fields (`TranslatableComboField` nested in a `Repeater` works: item schemas fall back to the parent record, so the combo returns item state).
- Module Resources relation manager is hidden for map modules; a new Sessions relation manager (create/edit/reorder/delete, "Resources" link) appears instead.
- New `CurriculumSessionResource` (edit page with its own Resources relation manager; not in navigation). Slug field validates `alpha_dash` + scoped unique and slugifies typed input.

## Public frontend

- `resources/views/curriculum/module.blade.php` rewritten: hero with "Module N", title, "Start Session 1" pill CTA, intro, Goal band (Lora display font), full-bleed olive Learning Outcomes band with numbered cards, session card grid (1/2/4-up). Direct module resources are no longer rendered on map modules.
- New `/curriculum/{key}/{session}` (`curriculum.session`) and `resources/views/curriculum/session.blade.php` with resources list and prev/next navigation. New `x-session-card` and `x-arrow-right` components.
- `app.css`: derived brand tokens (`--brand-surface`, `--brand-ink`, `--brand-line`, `--brand-font-display`, …), `.brand-pill`, `.brand-rule`, global `:focus-visible` ring.

## Seeder

`CurriculumSeeder` seeds numbers 1–5, goals and structured outcomes for map modules, the four community-needs sessions and its spike content, and backfills only `null` fields on existing rows. Legacy community-needs outcomes/description are replaced only when they still match the old seeded text.

## Gotchas discovered

- `HasMany::chaperone()` without a name throws `RelationNotFoundException` here because the inverse relation is `module()`, not `curriculumModule()`; pass the name explicitly.
- The lara-zeus relation-manager `Translatable` concern installs a content driver that re-wraps already locale-keyed `TranslatableComboField` state via `setTranslation()`, double-nesting JSON. `SessionsRelationManager::getFilamentTranslatableContentDriver()` returns `null`; table columns read translations explicitly with `TranslatableText::pick()` so they still follow the locale switcher.
- Filament validates before dehydration, so slug normalisation needs `mutateStateForValidationUsing` as well as `dehydrateStateUsing` for the `alpha_dash`/unique rules to see the slugified value.
- A Filament resource with only an `edit` page throws a `LogicException` from breadcrumbs; `CurriculumSessionResource` therefore has a minimal list page that is not registered in navigation.
- Never name a repeater sub-field after a real model attribute (`title`, `note`, …): `TranslatableComboField::formatStateUsing` would read the parent record's translations instead of item state.

## Verification

- `php artisan test`: 449 passed, 6 skipped (Meilisearch integration), 1 failed — `BrowseAllTest > it falls back to the database listing with a notice…`, which fails on a clean checkout of the branch and is unrelated.
- `vendor/bin/pint --dirty` clean; `npm run build` succeeds.
- Rendered on a throwaway SQLite database (`migrate --seed`): `/curriculum/community-needs`, its four session pages, `/curriculum/tech-assessment` (empty state), `/toolkit/farm-hack-box`; cross-module session slug 404s.
- Not done: running the two data migrations against a copy of the production MySQL database.
