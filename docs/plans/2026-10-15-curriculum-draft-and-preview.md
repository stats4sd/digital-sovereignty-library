# Curriculum: draft flag + gated preview

**Status:** Completed — see [docs/change-logs/curriculum-draft-and-preview.md](../change-logs/curriculum-draft-and-preview.md).

## Context

Once the curriculum is public, editors need to keep adding modules, sessions and content blocks without them appearing to learners mid-edit. Today nothing on the curriculum tables carries a visibility state; the only "draft" concept is Trove's `published_at`. This plan adds an `is_draft` flag at three levels (map module, session, item) and a `/preview/curriculum/...` mirror of the public pages, gated to editors/admins, that shows draft content with a banner.

Decisions agreed with Dave:

- Draft flag on **items, sessions and map modules** (intro/toolkit modules always live).
- **Public numbering skips drafts**; in preview a draft session shows a "Draft" badge instead of a number, so numbers never differ between preview and public.
- Preview covers **index, module and session pages**; links inside preview stay inside preview.
- **Everything new defaults to draft** (new map module, new session, new Builder block).

Pre-condition: the working tree has uncommitted "curriculum admin streamlining" edits (`CurriculumModuleResource`, `EditCurriculumModule`, `SessionsRelationManager`, `EditCurriculumSession`, several tests, `docs/change-logs/curriculum-admin-streamlining.md`). Commit those first; Group B touches the same files.

## Design

- **Column**: `boolean is_draft` on `curriculum_modules`, `curriculum_sessions`, `curriculum_session_items`. DB default `false` (existing rows stay live). Model default `true` via `protected $attributes` on `MapModule`, `CurriculumSession`, `CurriculumSessionItem` (not on `CurriculumModule`, so intro/toolkit stay live). No `published_at`: no lifecycle needed, avoids confusion with `TrovePublisher`.
- **Global scope** `app/Models/Scopes/DraftScope.php`, mirroring `PublishedScope`: adds `where <table>.is_draft = 0` unless `DraftVisibility::includesDrafts()`. Applied via trait `app/Models/HasDraftFlag.php` (boolean cast, `bootHasDraftFlag()` adds the scope, `scopeWithDrafts()` removes it) on `CurriculumModule`, `CurriculumSession`, `CurriculumSessionItem`. Hides drafts from lazy relations, `withCount`, and future code by default. Intro/toolkit rows are never draft, so the scope on the parent is harmless for them.
- **Visibility helper** `app/Support/Curriculum/DraftVisibility::includesDrafts(): bool` = `Filament::getCurrentPanel() !== null || request()->routeIs('curriculum.preview', 'curriculum.preview.*')`. Stateless (no static flag to leak between test requests). Not `runningInConsole()`: PHPUnit is console, and `usePublicContext()` tests must still see the scope. Seeder opts out explicitly with `withDrafts()` on its three `firstOrCreate` lookups, else hidden draft rows would be re-inserted and hit the unique indexes.
- **Controller** `app/Http/Controllers/CurriculumController.php` replaces the three route closures. Public `index/module/session` and `previewIndex/previewModule/previewSession` delegate to private `render*(…, bool $preview)`. Preview group: `Route::prefix('preview/curriculum')->middleware(['auth', 'can:preview-curriculum'])`, names `curriculum.preview`, `.show`, `.session`. `/toolkit/{key}` stays a closure. Name the existing `/resources/preview/{slug}` route `resources.preview`.
- **Gate** `preview-curriculum` => `User::canEdit()` in `AuthServiceProvider::boot()`. Guests go to the Filament login (`Authenticate::redirectTo` already does this) and return via `intended()`. Viewers get 403.
- **Context object** `app/Support/Curriculum/CurriculumContext.php` (`preview` derived from `DraftVisibility::includesDrafts()` so it can never disagree with the scope, + `mapUrl()`, `moduleUrl()`, `sessionUrl()`, `troveUrl()`, `learnerNamespace()`), `View::share('curriculum', …)` from every controller action, public and preview, so the shared value is always fresh.
- **Hiding**: done by `DraftScope`; the controller queries are identical in public and preview (no `when(!$preview, …)` branches). `CurriculumSessionItem::isRenderable()` keeps its signature: draft items never reach a public view, and in preview they render with a badge.
- **Numbering**: `CurriculumSession::number` becomes `?int`: `null` when draft, else position among non-draft siblings (`$siblings->reject->is_draft`). New `<x-session-label :session>` renders "Session N" or a `data-draft-badge` "Draft" badge; `session-card` drops its `number` prop.
- **Troves in preview**: eager-load `items.trove` with `withDrafts()` plus `trove.draft.troveType`, then `setRelation('trove', $trove->draft)` where a shadow draft exists, so preview shows the working version and everything downstream (`outlineLabel`, `isRenderable`, `items/trove.blade.php`) is unchanged. "View in library" uses `$curriculum->troveUrl()` → `resources.preview` in preview.
- **Learner store**: separate namespace in preview `dsl:v1:preview:{key}:{slug}` so editor test answers never surface as learner notes after publishing.
- **Banner**: `@stack('banner')` in `layouts/app.blade.php` after the header; `<x-curriculum-preview-banner>` modelled on `trove.blade.php:16-22`, with a link to the public URL; `<meta name="robots" content="noindex">` and "Preview: " title prefix in preview.

## Steps

### A. Schema + models
1. Migration `2026_10_15_100000_add_is_draft_to_curriculum_tables.php` (new migration, not folded: demo server has data).
2. `DraftVisibility` helper, `DraftScope`, `HasDraftFlag` trait (cast + scope + `withDrafts()`); use trait in `CurriculumModule`, `CurriculumSession`, `CurriculumSessionItem`.
3. `$attributes = ['is_draft' => true]` on `MapModule`, `CurriculumSession`, `CurriculumSessionItem`.
4. `CurriculumSession::number` nullable: `null` when draft, else position among `$siblings->reject->is_draft` (`app/Models/CurriculumSession.php:80-97`). The reject is needed in preview, where the scope is off.
5. `CurriculumSessionItem::isRenderable()` unchanged; update its docblock to mention `DraftScope`.
6. Factories (`CurriculumModuleFactory`, `CurriculumSessionFactory`, `CurriculumSessionItemFactory`): explicit `'is_draft' => false` in `definition()` + `draft()` state. Without this every public HTTP test breaks.

### B. Admin
7. `ItemDefinition::block()` (`app/Curriculum/Items/ItemDefinition.php:102`): `Toggle::make('draft')->default(true)->live()` after the Hidden `key`. Field is `draft`, not `is_draft` (CLAUDE.md rule on block sub-field names). New `labelFor(array $state)` wrapping `blockLabel()` + " (draft)"; `Block::label()` points at it (`TroveItem` overrides `blockLabel` without `parent`, so the suffix must live outside it).
8. `CurriculumSessionContentSync`: `toBlocks()` emits `'draft' => (bool) $item->is_draft`; `apply()` sets `is_draft = array_key_exists('draft', $data) ? (bool) $data['draft'] : ($item->exists ? $item->is_draft : true)`. `normalise()` already drops `draft` from config.
9. `CurriculumSessionResource::formSchema()`: `Toggle::make('is_draft')->label('Draft')->default(true)` (shared by edit form and relation-manager create modal). Number column `->placeholder('Draft')`.
10. `EditCurriculumSession::getHeaderActions()`: "Preview" action → `route('curriculum.preview.session', [module.key, slug])`, new tab, eye icon, visible when `can('preview-curriculum')`, tooltip "Shows the last saved version".
11. `SessionsRelationManager`: Status badge column (Draft/Live), number placeholder, "Preview" row action.
12. `CurriculumModuleResource`: `is_draft` toggle visible for map modules only; Status badge column blank for non-map rows.
13. `EditCurriculumModule::getHeaderActions()`: "Preview" → `curriculum.preview.show`, map modules only.

### C. Routes, controller, views
14. Gate in `AuthServiceProvider`.
15. `CurriculumContext`.
16. `CurriculumController` (same queries public and preview, scope does the filtering; preview additionally loads `items.trove` `withDrafts()` + `trove.draft.troveType` and swaps in the shadow draft; prev/next keep the index arithmetic over `$module->sessions`).
17. `routes/web.php`: controller routes keeping names `curriculum`, `curriculum.show`, `curriculum.session`; preview group; name `resources.preview`.
18. Layout `@stack('banner')`; banner component; push banner/noindex/title prefix from `curriculum/{index,module,session}.blade.php` when `$curriculum->preview`.
19. `<x-session-label>` component.
20. Rewire views to context + label: `partials/map.blade.php` (module URLs, Draft badge on draft nodes in preview), `curriculum/module.blade.php` (`$firstSession` = first non-draft, falling back to first; CTA via `sessionUrl`), `components/session-card.blade.php`, `curriculum/session.blade.php` (outline entries gain `'draft' => $item->is_draft`; heading label; back/prev/next; learner-store namespace), `components/session-outline.blade.php` (4 prev/next links; Draft tag per entry in sidebar and sheet), `components/curriculum-item.blade.php` (wrap draft items in `<div data-draft-item>` with dashed outline + badge; `.session-items h2[id]` still matches), `curriculum/items/trove.blade.php` (`troveUrl`).
21. New `t()` strings: Draft, Preview, banner sentence, "View public page". `translation:sync` later (needs key).

### D. Seeder + YAML
22. `CurriculumSeeder`: optional `draft:` on module (map only; throw on intro/toolkit), session, item; pass explicit `is_draft` on every create path (else model default makes all seeded rows drafts). The three `firstOrCreate` lookups (module `:73`, session `:183`, item key check) go through `withDrafts()` so an existing draft row is found, not duplicated. Not in `backfillModule`: existing rows untouched.
23. `digital-landscape.yaml`: `draft: true` on all five sessions (incl. further-reading, else it becomes public "Session 1"); module stays live.
24. `database/curriculum/README.md`: document `draft`, create-only semantics.
25. Demo server (no code): the five Module 1 sessions already exist live there; flip to Draft in admin or via tinker `CurriculumSession::whereHas('module', fn($q)=>$q->where('key','digital-landscape'))->update(['is_draft'=>true])`. Record in change log.

### E. Tests
26. Unit/model: `tests/Unit/Models/CurriculumSessionTest.php` (numbering skips drafts, null for draft, default draft); `CurriculumSessionItemTest.php` (default draft); new `tests/Feature/Models/DraftScopeTest.php` (under `usePublicContext()` drafts are hidden from direct queries, lazy relations and `withCount`; visible in panel context; visible when the request is a preview route; `withDrafts()` opts out; seeder re-run on a draft row does not duplicate); `CurriculumModuleInheritanceTest.php` (map defaults draft, intro/toolkit don't); `tests/Unit/Curriculum/ItemDefinitionsTest.php` (labelFor suffix for every type incl. trove; every block has draft toggle default true); `tests/Unit/Services/CurriculumSessionContentSyncTest.php` (round-trip; omitted flag keeps existing / creates draft; never in config; sync of a session with draft items deletes/keeps correctly, i.e. `whereNotIn('key', $seen)` runs with scope off in panel context).
27. `tests/Feature/Http/CurriculumTest.php`: draft module hidden from map + 404; draft session hidden + 404; gapless numbering with a middle draft (cards, CTA, heading, prev/next); CTA uses first live session.
28. `tests/Feature/Http/CurriculumSessionItemsTest.php`: draft items hidden + dropped from outline; empty state when all draft.
29. New `tests/Feature/Http/CurriculumPreviewTest.php`: guest → login redirect; viewer 403 (all three URLs); editor/admin see drafts with `data-draft-item`/`data-draft-badge` + banner; numbering identical preview vs public; links stay in `/preview`; unpublished trove item rendered + links to `resources.preview`; shadow draft shown in preview, canonical publicly (`TrovePublisher::draftFor`); separate learner namespace; noindex only in preview; public request after preview in same test has no banner.
30. Admin: `CurriculumModuleResourceTest` (new map module `is_draft` true; toggle round-trip, hidden for toolkit; relation-manager sessions default draft; status badge; preview actions editor-only); `CurriculumSessionContentTest` (block draft toggle hydrates/saves; session toggle; Preview header action URL); `CurriculumPoliciesTest` (gate).
31. `CurriculumSeederTest`: digital-landscape sessions draft, community-needs live; item-level draft from YAML; re-run doesn't flip; draft on intro/toolkit rejected.

### F. Docs
32. Save plan to `docs/plans/2026-10-15-curriculum-draft-and-preview.md`, Status In Progress → Completed with change-log link.
33. `docs/change-logs/curriculum-draft-and-preview.md` incl. deploy notes (migrate; flip Module 1 sessions; "new content is hidden by default" behaviour change for editors).
34. `CLAUDE.md` curriculum section: preview routes + gate, `CurriculumController`, `is_draft` semantics (DB default false / model default true / map only; `DraftScope` hides drafts everywhere except panel and preview contexts, `withDrafts()` opts out), `$curriculum` context, preview learner namespace, Builder `draft` toggle, new test file.

## Verification

- `php artisan test --testsuite=Unit,Feature`; `vendor/bin/pint --dirty`.
- `php artisan migrate` on a populated DB copy, `migrate:rollback --step=1`, `migrate`; all rows `is_draft = 0`.
- `php artisan app:fresh`: digital-landscape sessions draft, community-needs live.
- Manual (`npm run build`): guest → `/preview/curriculum/digital-landscape` redirects to login then back; `/curriculum/digital-landscape` shows no sessions; session URL 404. Viewer → 403 on all preview URLs, no Preview actions in admin. Editor → new module absent from `/curriculum`, badged on `/preview/curriculum`; new session shows Draft; block labels "(draft)"; Preview opens banner + markers, prev/next stay under `/preview`; untick drafts → public shows them numbered 1..n; draft the middle community-needs session → public numbers close up, preview shows badge. Troves: unpublished trove item hidden publicly, shown in preview linking to `/resources/preview/{slug}`; shadow draft title shows in preview only. DevTools: `dsl:v1:preview:…` vs `dsl:v1:…`. One preview page with `?locale=ar` for RTL banner/badge.

## Risks / flagged

- **Behaviour change for editors**: admin-created content is hidden until Draft is unticked. Say so in the change log.
- **Module numbers are stored** (`number`), not computed: a draft module between live ones leaves a gap in "Module N" labels. Out of scope per decisions; flag if renumbering is wanted.
- `DraftScope` applies in console and queue contexts (same as `PublishedScope`). Any future artisan command or job that must see drafts needs `withDrafts()`; the seeder is the only such caller today. Documented in CLAUDE.md.
- Public-hiding tests must call `usePublicContext()` (Filament registers the panel as current at boot in tests), the same gotcha as trove tests.
- Pre-existing failing test `carries the French translation of digital-landscape outcomes` is unrelated and will still fail.
