# Curriculum session items: Builder-edited mixed content, module STI, YAML-seeded content

**Date**: 2026-09-21
**Branch**: `content-updates`
**Plan**: [docs/plans/2026-09-21-curriculum-session-items.md](../plans/2026-09-21-curriculum-session-items.md)
**Spec**: [docs/specs/2026-09-21-curriculum-session-content-design.md](../specs/2026-09-21-curriculum-session-content-design.md)

Implements the plan above. A session's content is now an ordered list of typed **items** (prose, callout, note prompt, note canvas, note matrix, quiz, trove) instead of a trove pivot; admins edit them with a Filament `Builder` synced to rows; the public session page renders them as one long page with browser-side learner state; `CurriculumModule` is split into `IntroModule` / `MapModule` / `ToolkitModule`; admins can create and delete map modules and the learning map lays itself out; all seed content moved out of PHP into YAML under `database/curriculum/`, and Module 3's three sessions are seeded with the spike's content.

## Schema

- New `curriculum_session_items`: `curriculum_session_id` (cascade), `position`, `key` (uuid, unique per session, immutable), `type`, nullable `trove_id` (null on delete), translatable `intro` (trove items only), `config` JSON (shape per type). The `curriculum_session_trove` pivot never existed on a fresh database: its create/fill migrations were deleted rather than migrated away, since deployment runs `migrate:fresh` (see [collapse-migrations-for-fresh-deploy.md](collapse-migrations-for-fresh-deploy.md)).
- `CurriculumSession::troves()` is kept as a read-only view over `trove` items (`withPivotValue('type', 'trove')`), so counts and older tests still work.

## Item types (`app/Curriculum/Items/`)

- `ItemDefinition` is the contract: `configKeys()`, `translatableLeaves()`, `htmlLeaves()`, `integerLeaves()`, `booleanLeaves()`, `listLeaves()`, `rules()`, `block()` (the Builder block, with a hidden uuid `key`), `view()`, and `normalise()`, the single entry point for config written from anywhere (Builder state or seeder): fills every declared key, drops unknown top-level keys, re-indexes Repeater lists, strips empty locales, sanitises HTML, casts scalars, validates. `ItemRegistry` (singleton) holds the seven definitions.
- `heading` is optional on `prose`/`callout` (the spike content has headed prose) and required on activity types. Callout `kind`, matrix field `kind` and quiz question `kind` are fixed enums (`CalloutItem::KINDS`, `NoteMatrixItem::FIELD_KINDS`, `QuizItem::QUESTION_KINDS`). Quiz `correct` is a per-option flag; option/choice ids are unique per question/column, not per quiz.
- `TranslatableComboField::fromRecord(false)` skips the record-translation lookup so block sub-fields can be named after real columns (`intro`). `required()` on a RichEditor child now inspects TipTap documents (`isEmptyRichContent()`), and `HtmlSanitizer::isEmpty()` treats `<p></p>` as unfilled.

## Admin

- `EditCurriculumSession` has a "Session" tab and a "Content" tab (`App\Filament\Curriculum\SessionContentBuilder`). Both tabs are `App\Filament\Components\ErrorBadgedTab`, which badges the count of fields in error after a failed save. The Builder dehydrates normally (RichEditor HTML only exists after dehydration); `handleRecordUpdate()` hands the block list to `App\Services\CurriculumSessionContentSync::apply()` in one transaction, which matches rows on `data.key`, creates/updates/deletes, renumbers positions and turns a `ValidationException` into a "Block N (Type): …" notification with nothing half-written. `afterSave()` refills the form so every block holds its resolved key.
- The session-level `TrovesRelationManager` is gone; the module's `SessionsRelationManager` keeps create, reorder and delete and links to the session edit page. Session `slug` is locked after create (it namespaces learner state).
- `CurriculumModuleResource`: "New map module" (`CreateCurriculumModule`) fixes `section = map`, generates an immutable `key` from the English title (suffixed until unique, retried once on a concurrent duplicate) and `number = max + 1`; a delete action on map modules spells out the cascade. `CurriculumModulePolicy::create/delete` → `canEdit()` for map modules only; the policy is registered explicitly in `AuthServiceProvider` because the Gate only walks parent classes for registered policies.

## Public

- `curriculum/session.blade.php` renders `items` via `<x-curriculum-item :item>` → `curriculum/items/<type>.blade.php`; a trove item whose trove is unpublished or deleted renders nothing (`CurriculumSessionItem::isRenderable()`), and the empty state shows when nothing is renderable. Translatable config leaves are read with `CurriculumSessionItem::text('dot.path')` / `TranslatableText::pick()` with the usual locale fallback. Prose, callout, intro, headings and quiz stems carry `data-glossary-scope`.
- `<x-learner-store>` registers `Alpine.store('learner')` (one `localStorage` blob per session at `dsl:v1:{module.key}:{session.slug}`, debounced writes, flushed on `pagehide`, in-memory fallback when storage is unavailable) and `Alpine.data('curriculumQuiz')` (exact-set scoring from a JSON payload embedded per quiz, attempts appended under `{key}.attempts`, pass mark clamped to the rendered question count).
- `HtmlSanitizer` now allows `table/thead/tbody/tr/th/td` (`colspan`/`rowspan` only) and `.item-body table` is styled; the seeded "Why this matters" prose is a six-row table.

## Module inheritance and map

- STI is hand-rolled (`CurriculumModule::newFromBuilder()/newInstance()` keyed by `section`), not `tightenco/parental`: Parental's public `$hasParent` property breaks Livewire 4's lazy model proxies on PHP 8.4. Children (`IntroModule`, `MapModule`, `ToolkitModule`) are empty apart from the `ScopedToSection` trait and a factory pointer; `$table`, foreign key, joining segment and morph class are pinned to the parent. Routes use the child classes.
- `App\Support\Curriculum\MapLayout`: the trail is the original hand-drawn path; `nodes(N)` spaces N nodes at equal arc length along its two rows (bend excluded), server-side, in the same 0–100 space as the SVG viewBox, so no JS and no first-paint jump. Order is `MapModule::inMapOrder()` (`number`, then `id`).

## Seed content

- Everything the seeders create now lives in `database/curriculum/` as YAML (`modules/<key>.yaml`, `glossary.yaml`, `toolkit/tags.yaml`, `toolkit/tools/<pillar>.yaml`), every translatable field a `locale => value` map with the eleven translations merged in. `CurriculumSeeder` and `ToolkitToolsSeeder` keep their names but hold no content; `curriculum-translations/` and `toolkit-translations/` are deleted; `symfony/yaml` is a direct dependency (it was only reachable through `laravel/sail`, a dev dependency).
- Sessions carry `items: [{key, type, config}]`. The seeder runs each `config` through the same `normalise()` as the admin, prefixing any validation message with module/session/item so a YAML mistake is findable; an item whose `key` already exists is left alone, and a new one is appended after the session's current max `position` (the Builder renumbers rows on every save, so the YAML index could collide). `builds_toward` out of range, an unknown `type`, and a missing or duplicate `key` fail the seed loudly, and the whole run is one transaction. `trove` items are not seeded (none exist; the seeder throws if one appears).
- Module 3 (`community-needs`): session 1 has nine items (recall callout, "Why this matters" prose with the diagnostic-areas table, the Context Diagnostic Canvas, two example callouts with lessons, the diagnostic-statement prompt, key takeaways), session 2 four (Needs Matrix with a select column, Check Yourself prompt), session 3 two (the spike's engine-test quiz). Only trove items render `intro`, so each activity's instruction paragraph is an unheaded `prose` item placed before it. The quiz's `remediation` links and the matrix `idPrefix`/`noteId` were dropped (not in the spec); items are English only.
- Removed: `refreshLegacyCommunityNeeds()` (rewrote outcomes migrated by a since-deleted migration) and the per-locale merge code, with their tests. `backfillModule()` stays.

## Tests

New: `tests/Unit/Curriculum/ItemDefinitionsTest.php`, `tests/Unit/Services/CurriculumSessionContentSyncTest.php`, `tests/Unit/Support/MapLayoutTest.php`, `tests/Feature/Filament/CurriculumSessionContentTest.php`, `tests/Feature/Http/CurriculumSessionItemsTest.php`, `tests/Feature/Models/CurriculumModuleInheritanceTest.php`. Updated: `CurriculumModuleResourceTest`, `CurriculumSessionResourceTest`, `CurriculumPoliciesTest`, `CurriculumTest`, `tests/Feature/Seeders/*` (locale coverage and counts read from the YAML; items seeded in order; normalised config; admin edits survive re-seed). `php artisan test --testsuite=Unit,Feature`: 569 passed, 1 skipped. `tests/Unit/Support/HtmlSanitizerTest.php` locks the table allow-list.

## Known limitations / follow-ups

- Learner state is namespaced on the session `slug`; it is now immutable after create, but a module `key` rename would still orphan notes.
- Unknown nested keys inside `config` lists are stored as posted (only top-level keys are filtered).
- A hard-deleted trove leaves a trove block with an empty required Select that blocks saving until the block is removed.
- Items removed from the YAML are not deleted from the database on re-seed; content changes after launch are admin edits, not re-seeds.
- Builder visual hierarchy: see [docs/specs/2026-09-21-session-builder-visual-hierarchy-design.md](../specs/2026-09-21-session-builder-visual-hierarchy-design.md) (proposal, not started).
