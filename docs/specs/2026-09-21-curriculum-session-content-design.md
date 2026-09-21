# Curriculum session content: design & scoping

**Date**: 2026-09-21
**Status**: Accepted (decisions made with the project team; implementation plan at [docs/plans/2026-09-21-curriculum-session-items.md](../plans/2026-09-21-curriculum-session-items.md))
**Supersedes in part**: the "session page lists that session's troves" decision in [docs/plans/2026-09-08-curriculum-sessions-module-page.md](../plans/2026-09-08-curriculum-sessions-module-page.md)

## 1. Problem

The library's core unit is the **Trove**: a bundle of files, videos or links with a description. A learning course needs something troves do not provide: a page a learner works *through*, in order, with explanation between the resources and interactive exercises (notes, worksheets, self-checks) that only make sense inside the course.

The SvelteKit spike at `~/Projects/digital-sovereignty-course/spike-1` demonstrated the target learner experience for Module 3 ("Understanding Community Needs"): each session is one long page composed of typed regions (prose, callouts, note canvases, note matrices, a quiz), with learner notes persisted in the browser. That spike's *content model* is being brought into this Laravel app. Its distribution goals (offline single-file build, static hosting) are **not**.

Today (branch `content-updates`) the app already has `CurriculumModule` → `CurriculumSession` → ordered `Trove` pivot, and the session page renders a list of resource cards. This spec replaces the pivot with an ordered list of mixed content items.

## 2. Decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | A session's content is an **ordered list of items**. Each item is either a **LearningActivity** (course-only content) or a **Trove reference** (a library resource plus inline intro text). | Resolves the trove/module circularity: troves are *referenced from* the page, they are not the page. Activities are the third noun the previous model lacked. |
| D2 | Items are **database rows**, one table, discriminated by `type`. LearningActivity is **not** a separate Eloquent model in this version. | Activities have no life outside their item and are never shared. A trove is the opposite (shared, independently published, already a model), so it is a FK. One table also keeps FK integrity to troves and gives learner-state a stable row `key`. |
| D3 | Admin editing uses a **Filament `Builder`** field with one block per item type, backed by a **custom sync layer** that hydrates the Builder from item rows and writes rows back on save. | Builder is JSON-only and Repeater has one fixed schema; no Filament 5 plugin offers mixed blocks over relations. Owning ~150 lines of sync gets proper per-type schemas, drag reorder and insert-between, on relational storage. See §6. |
| D4 | **Learner state lives in `localStorage`**. No learner accounts. | Accounts are a barrier for the audience. Consequences accepted: quiz answer keys ship to the browser; activities are Alpine, not Livewire; item `key`s must be stable for life. |
| D5 | Initial item types for the demo: **prose, callout, note_canvas, note_matrix, note_prompt, quiz, trove**. | Matches the spike's Module 3 content. `note_prompt` (single free-text answer) is added because Module 3 Session 1's "diagnostic statement" needs it. |
| D6 | Sessions render as **one long page** at `/curriculum/{module}/{session}`. Pagination deferred until content proves too long. | Already routed; keeps prev/next navigation. |
| D7 | **Admins can add, reorder and delete learning-map modules and sessions.** The learning map draws N nodes **equally spaced along a fixed path**, recomputed from data. | Removes the hardcoded per-key node positions and the `canCreate() = false` on modules. |
| D8 | `CurriculumModule` splits into **`MapModule`** (has sessions) and **`ToolkitModule`** (has troves directly, no sessions) as **single-table-inheritance** children of `CurriculumModule`, discriminated by the existing `section` column. The intro module stays a plain `CurriculumModule`. | Same table, no data migration, translations untouched; each child gets only the relations and admin form it needs. |
| D9 | **Two kinds of intro text.** A per-item translatable `intro` ("why this is here, what to do, then come back") lives on the item row. A trove-level "how to use this resource" is a separate, optional improvement to `Trove` and is **out of scope** here. | They vary on different axes: per placement vs per resource. |
| D10 | Content authored and translated **in the database via the admin panel** (Spatie translatable JSON). Markdown-in-git and file-based translation pipelines are dropped. | Single authoring surface; existing `TranslatableComboField` conventions. Seeders remain the way *initial* content (Module 3) enters the DB. |
| D11 | Translatable `config` shape: **structure untranslated, strings translated per field**, i.e. each translatable leaf inside `config` is a locale dictionary `{en: …, fr: …}`, as `learning_outcomes` already does. | Avoids duplicating ids, answer keys and layout per locale. If this proves unwieldy for large quiz payloads, revisit; the item table does not change. |

## 3. Rejected alternatives (for the record)

- **Rich-text body per session, troves as cards.** Cheapest, but no worksheets or quizzes without shortcode hacks. Rejected because interactive activities are the product.
- **Extend `Trove` into a work-through-able lesson.** Blurs library and course; every resource becomes a potential lesson. Rejected.
- **Module = one Trove carrying a built web app.** Two toolchains, course content becomes a black box inside the library, drags offline back into scope. Rejected.
- **Repeater `relationship('items')` with a `type` select and `visible()` toggles.** Same table as chosen, native, but a single long form for all types. Kept as a fallback if the Builder sync proves fragile.
- **Separate `learning_activities` table via HasOne/MorphOne inside the item editor.** Nested relationship forms inside Repeater/Builder items save empty rows in Filament v4/v5 (filament#18859, #18656). Kept as the escape hatch in §5.

## 4. Data model

```
CurriculumModule            (existing table `curriculum_modules`; STI on `section`)
 ├─ MapModule               section = 'map'      hasMany CurriculumSession
 ├─ ToolkitModule           section = 'toolkit'  belongsToMany Trove (existing pivot)
 └─ (intro)                 section = 'intro'    belongsToMany Trove (existing pivot)

CurriculumSession           (existing)
 ├─ belongsTo MapModule
 ├─ translatable: title, summary, description
 ├─ slug, order_column, builds_toward
 └─ hasMany CurriculumSessionItem   ordered by position

CurriculumSessionItem       (NEW, table `curriculum_session_items`)
 ├─ curriculum_session_id   FK cascade
 ├─ position                unsigned int
 ├─ key                     string(36) uuid, unique per session, immutable   ← localStorage namespace + Builder block identity
 ├─ type                    string enum (App\Enums\CurriculumItemType)
 ├─ trove_id                nullable FK → troves (nullOnDelete); set iff type = trove
 ├─ intro                   json nullable, translatable (sanitised HTML)
 ├─ config                  json nullable; shape defined by `type` (§5)
 └─ timestamps

Trove (existing)
 └─ curriculumSessions()    belongsToMany via `curriculum_session_items` where type = trove
```

`curriculum_session_trove` no longer exists: its create/fill migrations were removed (no live data; deployment runs `migrate:fresh`), so trove attachments are only ever `type = trove` items.

## 5. Item types and `config` shapes

Each type is a PHP class in `app/Curriculum/Items/` implementing a small contract: its Filament `Block`, `config` validation/normalisation, and its Blade view. Translatable leaves are locale dictionaries (D11). `heading` is common to all activity types.

| type | config | interactive | learner state key |
|---|---|---|---|
| `prose` | `body` (t, HTML) | no | — |
| `callout` | `kind` ∈ recall/example/takeaways/check, `body` (t, HTML), `lesson` (t, optional) | no | — |
| `note_prompt` | `label` (t), `prompt` (t), `placeholder` (t), `rows` | textarea | `{key}` |
| `note_canvas` | `columns{label,prompt,notes}` (t), `fields[]{id,label(t),prompt(t)}` | one textarea per field | `{key}.{field.id}` |
| `note_matrix` | `rows`, `rowLabel` (t), `fields[]{id,label(t),placeholder(t),kind:text\|select,options[]}` | grid of inputs | `{key}.{row}.{field.id}` |
| `quiz` | `passMark`, `items[]{id,kind:pick-one\|pick-many,stem(t),options[]{id,text(t),correct},feedback{correct(t),incorrect(t)}}` | client-side scoring, attempt history | `{key}.attempts` |
| `trove` | — (uses `trove_id` + `intro`) | no | — |

Field/option `id`s inside `config` are author-entered short slugs, stable once learners have data. The admin UI warns on rename.

**Escape hatch.** If per-type `config` grows past what one Builder block comfortably edits, extract it to a `learning_activities` table with `item.activity_id` and edit it via a relation manager with per-type modals. The item table, `key`, public rendering and learner storage do not change.

## 6. Admin UX

- `EditCurriculumSession` gets a **Content** Builder (`addBetweenAction`, block icons, collapsible, drag reorder). Blocks: one per type. The trove block is a searchable Select over published canonical troves plus the `intro` field.
- Builder state is `dehydrated(false)`. `mutateFormDataBeforeFill` loads items into blocks (block `uuid` = item `key`); `afterSave` runs `CurriculumSessionContentSync` in a transaction: diff by key, create/update/delete, renumber `position`.
- The existing session `TrovesRelationManager` is removed. Module-level Troves relation manager stays for toolkit/intro modules.
- Map modules: `canCreate()` becomes true for `MapModuleResource`; `key` auto-generated from the English title and immutable afterwards; delete cascades sessions and items with a confirmation naming the counts.
- Translatable fields inside Builder blocks use `TranslatableComboField`; the double-nesting gotcha from the sessions change log applies (content driver must be `null` on this page).

## 7. Learner state (browser)

- One Alpine store per page, namespace `dsl:v1:{moduleKey}:{sessionSlug}:{itemKey}[…]`, autosave 400 ms after input stops, `try/catch` on every storage access (private mode, blocked storage).
- Quiz: pick-one/pick-many, exact-set scoring, pass mark, unlimited attempts with history, per-item feedback. Answer keys are in the page payload by design (D4).
- Deferred, not designed here: compiled summary page, JSON export/import, print stylesheet (all existed in the spike).

## 8. Learning map

Fixed SVG path (the existing curve). Node positions are computed at render time from the path length: node *i* of *N* sits at `getPointAtLength(L · i/(N−1))` (single node: midpoint). Mobile list fallback unchanged. Toolkit grid unchanged.

## 9. Seeded content

`CurriculumSeeder` gains `items` under each Module 3 session, ported from the spike's `content/module-3/session-*/*.md` (frontmatter → `config`, body → `body`/`intro`). Translations in `curriculum-translations/<locale>.php` under `modules.<key>.sessions.<slug>.items.<itemKey>.<field>`. Item `key`s are fixed uuids in the seeder so re-seeding is idempotent and learner data survives.

## 10. Out of scope

- Trove-level "how to use this resource" guidance (D9).
- Learner accounts, server-side progress, certificates.
- Offline / single-file build, PWA.
- Session pagination.
- Summary/export/print of learner notes.
- Toolkit module creation in the admin (pillars remain seeded).
- Glossary term management changes.

## 11. Open questions

- Should `note_matrix` support a `select` field kind in the first cut (the spike's Needs Matrix uses one)? Assumed **yes**, it is cheap.
- How much of Module 3 Sessions 3 and 4 content exists beyond the spike's test quiz? Seeder ports what exists; placeholders otherwise.
- Whether callout `kind` should be free-form or a fixed enum. Assumed fixed enum with four kinds; add via code.
