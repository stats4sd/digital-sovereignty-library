# Curriculum drafts + editor preview — Change Log

**Date**: 2026-10-15
**Branch**: `inline-troves`
**Plan**: [docs/plans/2026-10-15-curriculum-draft-and-preview.md](../plans/2026-10-15-curriculum-draft-and-preview.md)

## Summary

Learning-map modules, sessions and session items can now be **drafts**: hidden from learners, fully editable in the admin, and visible on an editor-only **preview** of the curriculum pages. This is the mechanism for continuing to build and revise the course after it is public.

Everything an editor creates from now on starts as a draft (new map module, new session, new content block). Nothing becomes visible to learners until its Draft toggle is switched off.

## Behaviour

- **Public pages** (`/curriculum`, `/curriculum/{module}`, `/curriculum/{module}/{session}`) never show draft rows. A draft module is absent from the map and its URL 404s; a draft session is absent from the module page, its URL 404s, and prev/next skip it; a draft item is left out of the page and the contents outline. Session numbers count only live sessions, so learners never see "Session 1, 2, 4".
- **Preview pages** (`/preview/curriculum`, `/preview/curriculum/{module}`, `/preview/curriculum/{module}/{session}`) are the same three pages with drafts included. They carry a banner ("Preview — draft content is shown. Only editors can see this page", with a link to the public page), a `noindex` meta tag, a "Preview: " title prefix, and every in-page link (map, back, prev/next, session cards) stays inside the preview. Draft modules, sessions and items are badged; a draft session shows a "Draft" badge where its number would be, so live numbers are identical in preview and public. Trove items render the trove's working version (the shadow draft if one exists, or an unpublished trove) and link to `/resources/preview/{slug}`. Learner notes written while previewing go to a separate `localStorage` namespace (`dsl:v1:preview:…`) so they never surface as a learner's own notes after publishing.
- **Access**: preview requires login and the `preview-curriculum` gate (`User::canEdit()`, i.e. editor or admin). Guests are sent to the admin login and returned afterwards; viewers get 403.
- **Admin**: Draft toggle on the session form (edit page and the "New session" modal), on the map-module form (hidden for intro/toolkit), and on every content block in the Builder (collapsed label reads "Prose: Heading (draft)"). Status badge (Draft / Live) on the module list and the sessions table; the number column shows "Draft" for a draft session. "Preview" button on the session edit page, the module edit page (map modules) and each row of the sessions table, opening the preview in a new tab. The preview shows the last **saved** state.

## Implementation

- Column `is_draft` (boolean, DB default `false`) on `curriculum_modules`, `curriculum_sessions`, `curriculum_session_items` — migration `2026_10_15_100000_add_is_draft_to_curriculum_tables`. Rows that predate the column stay live.
- `App\Models\HasDraftFlag` (cast, global `App\Models\Scopes\DraftScope`, `scopeWithDrafts()`) on the three models. `MapModule`, `CurriculumSession`, `CurriculumSessionItem` default new rows to `is_draft = true` via `$attributes`; intro/toolkit modules are never drafts.
- `App\Support\Curriculum\DraftVisibility::includesDrafts()` is the single rule the scope and the preview share: true inside a Filament panel or on a route named `curriculum.preview*`. The scope still applies in console/queue; the seeder's `firstOrCreate` lookups use `withDrafts()` so a hidden draft row is found rather than duplicated.
- `CurriculumSession::troves()` and `Trove::curriculumSessions()` pivot through item rows, which the scope cannot see through, so they add `wherePivot('is_draft', false)` under the same rule.
- `CurriculumSession::number` is `?int`: `null` for a draft, otherwise the position among live siblings. `<x-session-label :session>` renders the number or the Draft badge.
- The three public route closures became `App\Http\Controllers\CurriculumController`, registered once publicly and once under `/preview/curriculum` (middleware `auth` + `can:preview-curriculum`). Both share `$curriculum` (`App\Support\Curriculum\CurriculumContext`: `preview` flag, URL helpers, learner namespace) with the views; components that can render elsewhere fall back to a public context. The existing `/resources/preview/{slug}` route is now named `resources.preview`.
- Builder blocks carry a `draft` Toggle (default on) next to the hidden `key`; `CurriculumSessionContentSync` writes it to `is_draft` and keeps it out of `config`. `ItemDefinition::labelFor()` appends "(draft)" to every type's collapsed label.
- Seeder: optional `draft: true` on map modules, sessions and items (create-only; rejected on intro/toolkit). `digital-landscape.yaml` marks all five Module 1 sessions as drafts; the module itself stays live on the map.
- Layout gained `@stack('banner')`; new components `curriculum-preview-banner`, `session-label`.
- Test harness: `tests/Pest.php` now sets the admin panel as current in the global `beforeEach` (the behaviour CLAUDE.md had described but which was not actually true), so the panel-aware scopes are off by default and `usePublicContext()` turns them on.

## Tests

New: `tests/Feature/Http/CurriculumPreviewTest.php`, `tests/Feature/Models/DraftScopeTest.php`, `tests/Unit/Models/CurriculumSessionTest.php`. Extended: `CurriculumTest`, `CurriculumSessionItemsTest`, `CurriculumModuleResourceTest`, `CurriculumSessionContentTest`, `CurriculumPoliciesTest`, `CurriculumModuleInheritanceTest`, `CurriculumSeederTest`, `CurriculumSessionContentSyncTest`, `ItemDefinitionsTest`, `CurriculumSessionItemTest`. Full Unit+Feature run: 638 passed, 1 skipped.

## Deploying

1. `php artisan migrate` — every existing row becomes live (`is_draft = 0`).
2. **Module 1 sessions on the demo server already exist as live rows**, and the seeder never changes existing rows, so the YAML `draft: true` will not reach them. Switch the five `digital-landscape` sessions to Draft in the admin, or:
   ```php
   App\Models\CurriculumSession::whereHas('module', fn ($q) => $q->where('key', 'digital-landscape'))->update(['is_draft' => true]);
   ```
3. New `t()` strings need a `php artisan translation:sync`: "Preview", "Draft", "Draft content is shown. Only editors can see this page.", "View public page".
4. Tell editors: **new content is hidden by default** until its Draft toggle is switched off.

## Not done / follow-ups

- Module numbers (`CurriculumModule.number`) are stored, not computed, so a draft module between live ones leaves a gap in "Module N" labels on module pages. Agreed out of scope; revisit if it matters once modules are added post-launch.
- The preview module page's "The N sessions" count includes drafts (by design: it describes what is previewed).
- `/home` does not share `$curriculum`; it uses only the intro module and no context-dependent components.
