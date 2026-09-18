# Curriculum module page redesign + sessions

**Status:** Completed — see [docs/change-logs/curriculum-sessions-module-page.md](../change-logs/curriculum-sessions-module-page.md)

## Context

The public module page `/curriculum/{key}` currently shows title, description, a bullet list of outcomes and a flat list of troves attached directly to the module. The design prototype (SvelteKit spike at `~/Projects/digital-sovereignty-course/spike-1`, running on :5174) shows a richer page: "Module N" label + title, "Start Session 1" pill CTA, intro prose, a centred **Goal** band in a serif display font, a full-bleed olive **Learning Outcomes** band (numbered cards, each with a bold statement and an italic "In practice: …" note), and a grid of **session** cards ("SESSION 1 / title / Builds toward LO1 — summary / Start session →").

Structural change: learning-map modules get ordered **sessions**; troves attach to sessions, not to map modules. Toolkit pillars, farm-hack-box and the intro module keep attaching troves directly (ToolkitToolsSeeder, `/toolkit/{key}`, `/home` depend on it). Learning outcomes become structured. Decided with the user:

- Session card links to a **new session page** `/curriculum/{key}/{session}` listing that session's troves.
- Module→trove pivot **kept for non-map modules only**; existing map-module attachments migrate into an auto-created first session.
- Outcomes stored as a **structured repeater** (statement + in_practice, per-locale text inside each item).

The page keeps this site's existing header/footer/layout; only the body follows the design. No spike features beyond the module home (worksheets, notes, quiz, single-file build) are in scope.

## Schema

`curriculum_modules` (modify):
- `number` unsignedTinyInteger nullable (label "Module 3"; seeded 1..5 for map modules).
- `goal` json nullable, translatable.
- `learning_outcomes` json: meaning changes from translatable newline text to a **non-translatable array cast**: `[{"key": "<uuid>", "statement": {"en": "…"}, "in_practice": {"en": "…"} | null}, …]`. Drop from `$translatable`, add `'learning_outcomes' => 'array'` cast.

`curriculum_sessions` (new): id, `curriculum_module_id` FK cascade, `slug`, `order_column` unsignedInteger nullable, `title` json, `summary` json nullable, `description` json nullable (sanitised HTML), `builds_toward` string(36) nullable = the **`key` of an outcome item** (stable id, not a positional index — deleting/reordering outcomes must not silently re-point sessions), timestamps, unique(module, slug).

`curriculum_session_trove` (new): copy of `curriculum_module_trove` with `curriculum_session_id`; no timestamps.

## Steps

### A. Migrations (`database/migrations/2026_09_08_*`)
1. `100000_add_number_and_goal_to_curriculum_modules` — two columns; down drops them.
2. `100100_create_curriculum_sessions_table`, `100200_create_curriculum_session_trove_table` (sessions first; SQLite inline FK).
3. `100300_convert_curriculum_learning_outcomes_to_structured` — `DB` facade only (no Eloquent: casts changed, and `Trove` has a global scope). For each module: decode; skip if null/empty/already a list (`array_is_list`) so re-runs are safe; for each locale split lines, line *i* → item *i* `statement[locale]`, `in_practice = null`, `key = Str::uuid()`. `down()` no-op with docblock.
4. `100400_move_map_module_troves_to_sessions` — `DB` only, hardcode `'map'`. For each map module with pivot rows: find-or-insert session `slug='session-1'`, `order_column=1`, title = module title JSON verbatim; copy pivot rows preserving `order_column`; delete moved module pivot rows. `down()` no-op.

### B. Models
- `app/Models/CurriculumModule.php`: `$translatable` = title, subtitle, description, note, **goal**; casts `learning_outcomes => array`, `number => integer`; `sessions(): HasMany` ordered by `order_column, id`; keep `troves()`. Replace `outcomesList` accessor body (keep the name, `$module->outcomes_list`) to return `[['key','statement','in_practice'], …]` for the current locale via a fallback helper; filter items with empty statement.
- `app/Support/TranslatableText.php` (new): `pick(?array $translations, ?string $locale = null): ?string` — current locale, then the other `config('branding.locales')` keys, then any filled value. Spatie's fallback is not usable here (plain arrays, `config/translatable.php` unpublished).
- `app/Models/CurriculumSession.php` (new): `HasFactory`, `HasTranslations` (title, summary, description); `module()` belongsTo; `troves()` belongsToMany `withPivot('id','order_column')->orderByPivot('order_column')`; `saving` hook generates slug from title when blank, de-duplicated within the module; `number` accessor (`order_column`, else 1-based position among siblings); `buildsTowardOutcome` accessor → the module's `outcomes_list` item with matching `key` plus its 1-based position (for "LO3"), or null.
- `app/Models/Trove.php`: add `curriculumSessions()` beside `curriculumModules()` (~line 349).

### C. Policies (`app/Policies/`, auto-discovered)
- `CurriculumSessionPolicy` — copy `TagTypePolicy` (view true; mutate → `canEdit()`).
- `CurriculumModulePolicy` — same, but create/delete/forceDelete → false. Closes an existing gap: with no policy Filament allows everything, so viewers can currently edit curriculum content. (GlossaryTerm has the same gap; note in change log, out of scope.)

### D. Filament admin
`app/Filament/Resources/CurriculumModuleResource.php`:
- Add `number` TextInput (numeric, min 1) and `goal` `TranslatableComboField`(Textarea, 2 rows), both visible only for `section === SECTION_MAP` (same pattern as the existing `subtitle` toolkit gate).
- Replace the `learning_outcomes` combo with `Repeater::make('learning_outcomes')` → `reorderable()`, `collapsible()`, `itemLabel` = default-locale statement, `addActionLabel('Add outcome')`, schema: `Hidden::make('key')->default(fn () => (string) Str::uuid())`, `TranslatableComboField::make('statement')->childField(TextInput::class)->required()->columns(3)`, `TranslatableComboField::make('in_practice')->childField(Textarea::make('in_practice')->rows(2))`. Verified against Filament 5.6.8 source: repeater item schemas fall back to the parent record, so the combo's `formatStateUsing` sees `$record->statement === null` and returns item state. **Never name a repeater sub-field after a real module attribute** (title/note/description/goal). Fallback if the nesting misbehaves: plain `TextInput::make("statement.{$locale}")` per `config('app.locales')` inside the item (same on-disk shape). The inverse nesting already ships in `TroveResource` (`external_links`, `video_links`).
- Table: add `sessions_count`.
- `getRelations()`: `[TrovesRelationManager::class, SessionsRelationManager::class]`.

`CurriculumModuleResource/RelationManagers/TrovesRelationManager.php`: add `canViewForRecord()` → `section !== SECTION_MAP`.

`CurriculumModuleResource/RelationManagers/SessionsRelationManager.php` (new): relationship `sessions`; lara-zeus RM `Translatable` concern + `#[Reactive] public ?string $activeLocale` (mirror TrovesRelationManager); `canViewForRecord()` → map only; table `defaultSort('order_column')->reorderable('order_column')`, columns #, title, summary, builds-toward (resolved label), `troves_count`; `CreateAction` "Add session" with `mutateFormDataUsing` setting `order_column = max+1`; row actions `EditAction`, `Action 'Resources'` → `CurriculumSessionResource::getUrl('edit', …)`, `DeleteAction`. Modal form = shared `CurriculumSessionResource::formSchema(?CurriculumModule $module)`: title combo (TextInput required), slug TextInput ("leave blank to generate"), summary combo (Textarea 2 rows), `builds_toward` Select with options `key => "N. statement"` from the module's `outcomes_list`, placeholder None.

`app/Filament/Resources/CurriculumSessionResource.php` (new): resource-level `LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable` (not the page-level one — see `docs/change-logs/fix-edittagtype-translatable-trait.md`); `canCreate() false`; pages: `edit` only (navigation is built explicitly in `AdminPanelProvider`, so nothing appears in the menu; add a `ListCurriculumSessions` page only if Filament requires an index route). Form: `Placeholder` showing parent module + back link, `formSchema()` fields, plus `description` combo wrapping `RichEditor` with `HtmlSanitizer::clean()` dehydration copied from the module resource. `getRelations()`: its own `TrovesRelationManager` — copy of the module one (heading "Resources in this Session", same published-canonical `AttachAction` filter, same `recordUrl`, no `canViewForRecord`). `Pages/EditCurriculumSession.php` mirrors `EditCurriculumModule` (incl. the `activeLocale` mount default) with a header action back to the module edit page.

### E. Seeders & factories
- `database/seeders/Prep/CurriculumSeeder.php`: keep `firstOrCreate` on key. Map payloads gain `number` (1..5 in seeder order; community-needs = 3), `goal`, structured `learning_outcomes` (existing strings → statements, `key` uuid, `in_practice` null). Backfill blank `number/goal/learning_outcomes` on existing rows. For `community-needs`: goal, 4 outcomes with in_practice and 4 sessions from the spike (`understanding-your-operational-context`, `defining-what-you-actually-need`, `deciding-what-belongs-in-a-digital-system`, `why-data-governance-starts-with-your-needs`; summaries per spike; `builds_toward` = key of outcome 1..4); sessions via `sessions()->firstOrCreate(['slug'], [...])`. Replace the three legacy community-needs outcomes only when stored statements exactly match a `LEGACY_COMMUNITY_NEEDS_OUTCOMES` const (edit-preserving, idempotent). Optional, same guard: swap the community-needs description for the spike intro paragraph. No placeholder sessions for the other map modules. `DatabaseSeeder`/`ToolkitToolsSeeder` unchanged.
- `database/factories/CurriculumModuleFactory.php`: new outcomes shape, `goal`, `number` (map state), `withSessions(int)` state. `CurriculumSessionFactory.php` (new).

### F. Frontend
`resources/css/app.css`:
- `@theme`: `--color-brand-surface/-surface-muted/-ink/-ink-muted/-line`, `--font-display: var(--brand-font-display)`.
- `:root`: `--brand-surface #FFFFFF`, `--brand-surface-muted #F6F1DD`, `--brand-ink #2F2A20`, `--brand-ink-muted #6B6355`, `--brand-line #DED6BD`, `--brand-font-display 'Lora', Georgia, serif` (Lora is already loaded by `layouts/app.blade.php` line 11 — do not add an `@import`). Do **not** set `body { color }`.
- `@layer base`: `:focus-visible { outline: 3px solid var(--brand-secondary); outline-offset: 3px }` (none exists today; site-wide public only, admin theme doesn't import app.css).
- `@layer components` (Curriculum block): `.brand-rule` (`w-6 shrink-0`, bg primary), `.brand-pill` (inline-flex items-center gap-4 rounded-full px-8 py-4 font-bold shadow-lg, bg primary, `color:#fff` — must live in the components layer to beat the base `a { color }` rule), `.brand-pill:hover { opacity:.9 }`, `.bg-brand-primary .glossary-term:hover` → cream.

New components: `resources/views/components/arrow-right.blade.php` (svg, `$attributes` passthrough, no default classes) and `resources/views/components/session-card.blade.php` (props module, session, number; the `<a>` card with classes `group flex grow basis-full flex-col rounded-2xl border border-brand-line bg-brand-surface p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-brand-primary hover:shadow-lg md:basis-[calc(50%-0.75rem)] lg:basis-[calc(25%-1.125rem)]`; "Session N" label, h3 with explicit `text-xl font-bold` (base layer sets h3 = text-lg), "Builds toward LOn — summary", "Start session →").

`resources/views/curriculum/module.blade.php` — rewrite. `<main>` has no container, so sections are full-bleed with an inner `mx-auto w-full max-w-6xl px-6 sm:px-8 lg:px-12` wrapper:
1. Hero: back link to learning map; `@if number` "Module N" (text-3xl md:text-4xl bold primary); h1 (text-4xl md:text-5xl bold leading-[1.1] text-brand-ink); `@if first session` `.brand-pill` CTA "Start Session 1" + sublabel first-session title + arrow; description (`.description mt-10 max-w-3xl text-lg leading-relaxed`, `data-glossary-scope`); `note-callout` if note.
2. Goal (`@if goal`): `max-w-4xl`, `border-y-2 border-brand-line py-10 text-center`, "GOAL" label (text-xs bold tracking-[0.25em] uppercase secondary), h2 `font-display text-xl md:text-3xl font-bold text-brand-primary`.
3. Outcomes (`@if count`): `bg-brand-primary text-white py-14 lg:py-16`, grid `lg:grid-cols-[18rem_1fr] lg:gap-16`; left h2 "Learning Outcomes" + "By the end of this module, you will be able to:"; right `<ol class="flex flex-col gap-5">` of `li.flex.gap-5.rounded-2xl.bg-white/10.p-6` with numbered circle (`h-10 w-10 rounded-full bg-brand-bg text-brand-primary font-bold`, `$loop->iteration`), statement `text-lg font-bold leading-snug`, in_practice `mt-2 italic opacity-85` prefixed "In practice:".
4. Sessions: `.brand-rule` beside h2 (`n('The %d session','The %d sessions',…)` or `t('Sessions')` when empty) + subtitle; `mt-10 flex flex-wrap gap-6` of `x-session-card`; empty state "Sessions for this module are coming soon." Direct `$module->troves` no longer rendered.

`resources/views/curriculum/session.blade.php` (new, `max-w-4xl`): back link to module; "SESSION N" label; h1; "Builds toward LOn — statement" (from `buildsTowardOutcome`); description prose; `.divider` + h2 "Resources" + `x-curriculum-resource-row` loop (empty state "Resources for this session are coming soon."); prev/next session cards (`border-t border-brand-line pt-8`); "Back to module home".

`resources/views/curriculum/pillar.blade.php` line ~27: `{{ $outcome['statement'] }}` (+ optional in_practice span) — otherwise fatals on the new accessor shape. Learning map/index: no change (node order is hardcoded by key; copy says "explore in any order").

`routes/web.php`: `curriculum.show` → `with('sessions')`; add `GET /curriculum/{key}/{session}` named `curriculum.session`: load map module by key with sessions, `firstWhere('slug')` on the loaded collection (a slug from another module 404s), `load('troves.troveType')`, compute prev/next from the collection. `PublishedScope` hides drafts automatically.

### G. Docs (project CLAUDE.md work patterns)
- Save this plan to `docs/plans/2026-09-08-curriculum-sessions-module-page.md` with a **Status:** line; on completion write `docs/change-logs/curriculum-sessions-module-page.md` linking the plan and mark the plan Completed.

## Tests
- `tests/Feature/Filament/CurriculumModuleResourceTest.php`: replace newline outcomes assertion with repeater save (per-locale statement, in_practice, `key` present, no double nesting); `number`/`goal` round-trip; TrovesRelationManager hidden for map / shown for toolkit; Sessions RM create (order_column 1) + reorder.
- `tests/Feature/Filament/CurriculumSessionResourceTest.php` (new): edit saves translatable fields, description sanitised; builds_toward options from module outcomes; trove RM attaches `publishedTrove()`, rejects `draftTrove()` (`assertHasTableActionErrors`), pivot ordering.
- `tests/Feature/Seeders/CurriculumSeederTest.php`: still 11 modules; community-needs has 4 sessions in order with expected slugs and builds_toward → outcomes 1..4; numbers 1..5; idempotent re-run preserves an edited session title.
- `tests/Feature/Http/CurriculumTest.php`: module hero (Module N, title, Start Session 1, first-session title, goal, session route); hidden number/goal/CTA + empty state; outcomes with "In practice:"; session cards; directly-attached trove no longer shown; session page (outcome line, description, troves in order, drafts hidden); prev/next present/absent at ends; 404 for unknown slug, slug of another module, `/toolkit/{key}/anything`. Update the existing "Learning outcomes" casing assertion.
- Unit: `TranslatableText::pick()` fallback order; `outcomes_list` with missing locale / null / legacy shape (returns `[]`); session slug de-dup, `number`, `buildsTowardOutcome` unknown key → null.
- `tests/Feature/Migrations/CurriculumSessionsMigrationTest.php`: insert legacy-shaped rows via `DB::table()`, `include` and `->up()` the two data migrations, assert converted JSON and moved pivots. Drop if brittle on SQLite.

## Verification
```bash
vendor/bin/pint --dirty
php artisan test --filter=Curriculum
php artisan test --filter=ToolkitToolsSeederTest      # pillars keep direct troves
php artisan test                                      # full gate
npm run build && grep -o "lg:grid-cols-\[18rem_1fr\]" public/build/assets/*.css
php artisan migrate:fresh --seed
```
Manual: `/curriculum/community-needs` (olive band edge-to-edge, no horizontal scrollbar; goal in Lora; pill white-on-olive; 1/2/4-up cards at <768/768–1023/≥1024; focus rings; glossary tooltips on intro and outcome text), `/curriculum/community-needs/understanding-your-operational-context` (prev/next, resources), `/curriculum/tech-assessment` (no sessions → empty state, no CTA), `/toolkit/knowledge`, `/toolkit/farm-hack-box`, `/home` still render. Admin: Tech Assessment shows outcomes repeater + Sessions RM and no Resources RM; Knowledge pillar shows Resources RM only; create a session → "Resources" action → attach published trove → drag reorder → visible on public session page. Run `php artisan migrate` against a copy of a MySQL DB that already holds curriculum data.

## Risks
- `TranslatableComboField` inside `Repeater` is unproven here (mechanism verified in Filament source; fallback described in D). Budget an hour.
- Adding `CurriculumModulePolicy` gates edit pages on `update`; existing tests run as editor so stay green — check none run as viewer.
- Base-layer rules in app.css (`a { color }`, `h2`/`h3` sizes) mean every heading/anchor in new markup needs explicit classes.
- Site-wide `:focus-visible` change on public pages; scope to curriculum pages if unwanted.
- `outcomes_list` shape change: grep confirms only `module.blade.php` and `pillar.blade.php` consume it.
