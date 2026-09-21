# Curriculum session items: Builder-edited mixed content on session pages

**Status:** In Progress (Phase 0 complete 2026-09-21)

**Spec:** [docs/specs/2026-09-21-curriculum-session-content-design.md](../specs/2026-09-21-curriculum-session-content-design.md)
**Branch:** `content-updates` (builds on the completed sessions work in [2026-09-08-curriculum-sessions-module-page.md](2026-09-08-curriculum-sessions-module-page.md))

## Goal

Replace the session → trove pivot with an ordered list of typed `CurriculumSessionItem` rows (prose, callout, note_prompt, note_canvas, note_matrix, quiz, trove), edited in the admin with a Filament `Builder` synced to rows, rendered as one long session page with `localStorage` learner state. Split `CurriculumModule` into `MapModule`/`ToolkitModule` (STI), let admins create/delete map modules, and make the learning map lay itself out. Seed Module 3 content from the spike.

## Non-goals

See spec §10. In particular: no trove-level guidance field, no learner accounts, no summary/export, no pagination.

## Phases

Phases are ordered so each leaves the suite green and the site working. Phase 0 is a throwaway spike that de-risks the two unknowns before any schema lands.

---

### Phase 0 — Spike the two unknowns (½ day, throwaway branch)

**0.1 `TranslatableComboField` inside a `Builder` block.** On a scratch field on `EditCurriculumSession`, put a `Builder` with one block containing `TranslatableComboField::make('body')`. Confirm: (a) state round-trips as a flat locale dict per block, (b) `formatStateUsing` does **not** fall back to the parent record's translations (the change-log gotcha: never name a block sub-field after a real model attribute; `body`, `intro`, `heading` are safe, `title`/`description`/`summary` are not), (c) the lara-zeus content driver must be nulled on the page.
**0.2 `Builder` block identity.** Confirm Filament 5 Builder exposes a stable per-block uuid in state (`->generateUuidUsing()` / item keys) that survives reorder and is available in `afterSave`. If not, add `Hidden::make('key')->default(fn () => Str::uuid())` to every block.

Exit: written note in this plan under "Spike findings" and a decision to proceed with Builder or fall back to the Repeater variant (spec §3).

---

### Phase 1 — Schema and models

**1.1 Enum** `app/Enums/CurriculumItemType.php` (string-backed): `Prose, Callout, NotePrompt, NoteCanvas, NoteMatrix, Quiz, Trove`; `label()`, `icon()`, `isActivity()`.

**1.2 Migration** `2026_09_21_100000_create_curriculum_session_items_table`:
`id`, `curriculum_session_id` FK cascadeOnDelete, `position` unsignedInteger, `key` string(36), `type` string(32), `trove_id` nullable FK nullOnDelete, `intro` json nullable, `config` json nullable, timestamps. Unique `(curriculum_session_id, key)`; index `(curriculum_session_id, position)`; index `trove_id`.

**1.3 Data migration** `2026_09_21_100100_move_session_troves_to_items` (DB facade only, re-run safe, `down()` no-op with docblock): for each `curriculum_session_trove` row ordered by `order_column, id` → insert item `type='trove'`, `position` = running index, `key` = `Str::uuid()`, `trove_id`, `intro = null`. Skip sessions that already have items. Then `2026_09_21_100200_drop_curriculum_session_trove_table`.

**1.4 Model** `app/Models/CurriculumSessionItem.php`: `HasTranslations` with `$translatable = ['intro']`; casts `type => CurriculumItemType`, `config => array`, `position => integer`; `session()` belongsTo; `trove()` belongsTo (note `PublishedScope` applies outside the panel, so an unpublished trove yields `null` publicly, and the view must handle it); `booted`: `creating` sets `key` if blank; `updating` throws if `key` changed. Accessor `definition(): ItemDefinition` (Phase 2).

**1.5 `CurriculumSession`**: `items()` hasMany ordered by `position`, `chaperone('session')`; `troves()` redefined as `belongsToMany(Trove::class, 'curriculum_session_items')->wherePivot('type', 'trove')->withPivot('id', 'position', 'intro')->orderByPivot('position')` so existing counts/tests keep working. `Trove::curriculumSessions()` likewise re-pointed. `TroveObserver`/reindex hooks: check `tests/Feature/Search/ReindexTriggersTest.php` for anything keyed on the old pivot table name.

**1.6 Factory** `CurriculumSessionItemFactory` with states per type carrying minimal valid `config`.

**Tests**: migration test (pivot rows become items in order; idempotent), model unit test (key immutability, `troves()` filters by type), `TroveStateParityTest` still green.

---

### Phase 2 — Item type definitions

**2.1 Contract** `app/Curriculum/Items/ItemDefinition.php` (abstract): `type(): CurriculumItemType`, `block(): Filament\Forms\Components\Builder\Block`, `rules(): array` (config validation, used by a `dehydrateStateUsing` normaliser), `normalise(array $config): array`, `view(): string` (`curriculum.items.<type>`), `translatableLeaves(): array` (dot paths, used by the seeder translation merge and the admin locale switcher).
**2.2 Registry** `app/Curriculum/Items/ItemRegistry.php`: `all()`, `for(CurriculumItemType)`; bound in `AppServiceProvider`.
**2.3 Definitions**: `ProseItem`, `CalloutItem`, `NotePromptItem`, `NoteCanvasItem`, `NoteMatrixItem`, `QuizItem`, `TroveItem`. Config shapes per spec §5. Translatable leaves use `TranslatableComboField`; repeated sub-structures (`fields[]`, quiz `items[]`, `options[]`) use nested `Repeater`s with an `id` TextInput (`alpha_dash`, helper text warning that renaming orphans learner notes) and a `Hidden` uuid fallback.
**2.4 Common** `heading` (translatable) on every activity block; `TroveItem` block = `Select::make('trove_id')` searchable over `Trove::query()->whereNotNull('published_at')->whereNull('published_id')` with `getOptionLabelFromRecordUsing(title)` + `TranslatableComboField::make('intro')` (RichEditor, sanitised).

**Tests**: unit test per definition that `normalise()` + `rules()` accept the seeder fixtures and reject a missing required leaf.

---

### Phase 3 — Admin: Builder + sync

**3.1 Sync service** `app/Services/CurriculumSessionContentSync.php`: `toBlocks(CurriculumSession): array` and `apply(CurriculumSession, array $blocks): void`. `apply` runs in a transaction: index existing items by `key`; for each block in order → update or create (`type`, `trove_id`, `intro`, `config`, `position`); delete items whose key is absent; positions renumbered 0..n-1. Pure array in / rows out, no Filament dependency, so it is unit-testable.
**3.2 Builder field** `app/Filament/Curriculum/SessionContentBuilder.php` (static `make()`): `Builder::make('content')->blocks(ItemRegistry::blocks())->dehydrated(false)->reorderableWithButtons()->addBetweenAction()->blockIcons()->collapsible()->blockNumbers(false)->itemLabel(heading or trove title)`.
**3.3 `EditCurriculumSession`**: add the Builder under the existing fields; `mutateFormDataBeforeFill` → `$data['content'] = $sync->toBlocks($record)`; `afterSave` → `$sync->apply($record, $this->data['content'] ?? [])`; `getFilamentTranslatableContentDriver(): null` (gotcha from previous change log). Remove `RelationManagers\TrovesRelationManager` from `CurriculumSessionResource::getRelations()` and delete the class. Session table `troves_count` column → `items_count`.
**3.4 `SessionsRelationManager`** on the module: add a "Content" link/action to the session edit page (already has "Resources" link; rename).
**3.5 Policy**: `CurriculumSessionPolicy` already gates mutations by `canEdit()`; no item policy needed since items are only reachable through the session form.

**Tests** (`tests/Feature/Filament/CurriculumSessionContentTest.php`): fill the edit form with blocks → rows created in order; reorder → positions change, keys unchanged; remove block → row deleted; trove block persists `trove_id`; translatable `intro` stored flat; viewer gets 403.

---

### Phase 4 — Public rendering + learner state

**4.1 Views** `resources/views/curriculum/items/{prose,callout,note_prompt,note_canvas,note_matrix,quiz,trove}.blade.php` and a dispatcher component `<x-curriculum-item :item />` that includes `$item->definition()->view()`. Port markup from the spike's Svelte layouts (`Prose`, `Callout`, `NotePrompt`, `NoteCanvas`, `NoteMatrix`, `Quiz`) to Blade + Tailwind using the brand tokens added in the sessions change log. Prose/callout/intro regions get `data-glossary-scope`. Trove item: `intro` above a `<x-curriculum-resource-row>`; hidden entirely when `$item->trove` is null (unpublished).
**4.2 `session.blade.php`**: replace the Resources list with `@foreach($session->items as $item) <x-curriculum-item :item /> @endforeach`; empty state kept. Route closure eager-loads `items.trove.troveType`.
**4.3 Learner state** `resources/views/components/learner-store.blade.php` (included from `session.blade.php` only): Alpine `Alpine.store('learner')` with `namespace`, `get(path)`, `set(path, value)` (debounced 400 ms write), all storage access in try/catch; key `dsl:v1:{module.key}:{session.slug}`. Note items bind inputs via `x-model` on `$store.learner` paths per spec §5. A small "Saved in this browser" status line per note item.
**4.4 Quiz**: inline Alpine component per quiz item: selection state, `submit()` exact-set scoring, pass mark, attempts array persisted under `{key}.attempts`, per-item feedback, "try again". Answer key is read from the item's JSON script tag.
**4.5 RTL/a11y**: inputs and radio groups labelled; `rtl:` variants where the spike used directional spacing.

**Tests** (`tests/Feature/Http/CurriculumSessionItemsTest.php`): session page renders each type's heading; trove item shows title and intro; unpublished trove item hidden; empty session shows placeholder; quiz payload embedded as JSON; glossary scope present on prose. Browser-side behaviour is manual (see Verification).

---

### Phase 5 — Module STI, admin create/delete, self-laying-out map

**5.1 STI**: add `tightenco/parental` (or hand-roll `newFromBuilder` on `CurriculumModule` keyed by `section`; prefer the package). `App\Models\MapModule` (`section = 'map'`, `sessions()`), `App\Models\ToolkitModule` (`section = 'toolkit'`, `troves()`), parent keeps shared attributes, translations and `outcomes_list`. `CurriculumSession::module()` returns `MapModule`. Update route closures and seeders to use child classes where they filter by section.
**5.2 Admin**: `CurriculumModuleResource` stays as the umbrella list (all sections). `canCreate()` → true; create form fixed to `section = map`, `key` generated from English title (`Str::slug`, uniqueness-suffixed, immutable after create), `number` = max+1. Delete action on map modules with confirmation listing session/item counts; `CurriculumModulePolicy::create/delete` → `canEdit()` for map modules only (intro and toolkit remain undeletable).
**5.3 Map layout**: `partials/map.blade.php` drops the position array; renders nodes with `data-index` inside the SVG wrapper; small inline Alpine/JS on init and resize positions each node at `path.getPointAtLength(L * i / (N - 1))` (N = 1 → midpoint), converting SVG to percentage. Mobile list unchanged. Order = `number`, then `id`.
**5.4** `CurriculumTest`: map renders N nodes for N map modules; new module appears; toolkit unaffected.

---

### Phase 6 — Seed Module 3 content

**6.1** `CurriculumSeeder`: session definitions gain `items => [ ['key' => '<fixed uuid>', 'type' => 'callout', 'config' => [...], 'intro' => ...], … ]`. Port `spike-1/content/module-3/session-1/*.md` (recall, why-this-matters, canvas, example-makueni, example-cocoa, diagnostic-statement, key-takeaways), `session-2/*.md` (needs-matrix, check-yourself), `session-3/quiz.md`. Existing seeded trove attachments stay (already migrated to trove items by 1.3; seeder must not duplicate: `firstOrCreate(['key' => …])`).
**6.2 Translations**: `curriculum-translations/<locale>.php` → `modules.community-needs.sessions.<slug>.items.<itemKey>` with the translatable leaves from `ItemDefinition::translatableLeaves()`. English only in the first pass if translations are not ready; merge is tolerant of missing keys as today.
**6.3** `CurriculumSeederTest`: items seeded in order, idempotent on re-run, translation merge fills a leaf.

---

### Phase 7 — Docs and cleanup

- Change log `docs/change-logs/curriculum-session-items.md`; set this plan's Status to Completed with the link.
- `CLAUDE.md`: update the Curriculum layer section (items, Builder sync, STI modules, learner state, map layout, `curriculum_session_trove` gone).
- Remove dead code: session `TrovesRelationManager`, hardcoded map positions, `canCreate() = false` on modules.
- `vendor/bin/pint --dirty`, `npm run build`.

## Files to touch (summary)

| Area | Files |
|---|---|
| Enums / contracts | `app/Enums/CurriculumItemType.php`, `app/Curriculum/Items/*` |
| Models | `app/Models/CurriculumSessionItem.php` (new), `CurriculumSession.php`, `CurriculumModule.php`, `MapModule.php`, `ToolkitModule.php` (new), `Trove.php` |
| Migrations | three new `2026_09_21_*` files |
| Services | `app/Services/CurriculumSessionContentSync.php` |
| Filament | `CurriculumSessionResource.php` + `Pages/EditCurriculumSession.php`, `app/Filament/Curriculum/SessionContentBuilder.php`, `CurriculumModuleResource.php` + pages/relation managers, delete `CurriculumSessionResource/RelationManagers/TrovesRelationManager.php` |
| Policies | `CurriculumModulePolicy.php` |
| Views | `curriculum/session.blade.php`, `curriculum/items/*.blade.php` (new), `components/curriculum-item.blade.php`, `components/learner-store.blade.php` (new), `curriculum/partials/map.blade.php` |
| Routes | `routes/web.php` (eager loads, child model classes) |
| Seeders | `database/seeders/Prep/CurriculumSeeder.php`, `curriculum-translations/*.php` |
| Tests | new: migration, model, definitions, admin content, public items, seeder; updated: `CurriculumTest`, `CurriculumSessionResourceTest`, `CurriculumModuleResourceTest` |
| Docs | this plan, spec, change log, `CLAUDE.md` |

## Risks and mitigations

- **Builder + `TranslatableComboField` nesting misbehaves** → Phase 0 finds out first; fallback is the Repeater variant on the same table (spec §3).
- **Block identity lost on reorder** → Phase 0.2; explicit `Hidden` key per block if Filament's uuid is not exposed.
- **Item `id`s inside `config` renamed after launch orphan learner notes** → helper text + a `normalise()` that refuses empty ids; a future "rename with migration" is out of scope.
- **`PublishedScope` hides drafts publicly** → trove items with null `trove` are skipped in the view; admin shows a badge on such blocks.
- **STI breaks lara-zeus `Translatable` or Filament record resolution** → `CurriculumModuleResource::$model` stays the parent; child classes only add relations. If `parental` conflicts with `HasTranslations`, hand-roll `newFromBuilder`.
- **Nightly `scout:import` / reindex hooks reference the dropped pivot** → grep for `curriculum_session_trove` and `curriculumSessions` before 1.3 lands.
- **Data migrations on production MySQL** → run 1.3 against a copy first (the previous plan's unfinished item applies here too).

## Verification

Automated: `php artisan test --testsuite=Unit,Feature` green (baseline: 449 passed / 1 pre-existing `BrowseAllTest` failure noted in the previous change log); `vendor/bin/pint --dirty` clean; `npm run build`.

Manual, on a fresh `php artisan app:fresh`:

1. `/admin` → Curriculum → community-needs → Sessions → Session 1 → Content: blocks load in seeded order; drag reorder, insert-between, delete, save; reload shows the same; DB rows match.
2. Switch admin locale; edit a French `body`; English untouched.
3. `/curriculum/community-needs/session-1`: all item types render; type into the canvas; reload; text persists; open in a private window: empty and no console errors.
4. Session 3 quiz: wrong then right answers; attempt history shows both; passes at pass mark.
5. Create a new map module in the admin; `/curriculum` map shows six equally spaced nodes; delete it; five nodes.
6. `?locale=ar`: session page and note inputs render RTL without overflow.
7. Unpublish a trove referenced by an item; session page hides that item; republish; it returns.

## Spike findings

Phase 0 done 2026-09-21 on the throwaway branch `spike/session-builder-translatable` (one commit: a scratch `Builder::make('spike_content')->dehydrated(false)` on `CurriculumSessionResource`, `mutateFormDataBeforeFill`/`afterSave` capture hooks on `EditCurriculumSession`, and `tests/Feature/Filament/SpikeBuilderTranslatableTest.php`, all green). Nothing from that branch is merged.

**Decision: proceed with the Builder (D3). No fallback to the Repeater variant needed.**

**0.1a State shape.** A `TranslatableComboField` inside a Builder block hydrates and round-trips as a flat locale dictionary per block (`data.body = ['en' => …, 'fr' => …]`). Locales without a value are present as `null`, so the sync layer must strip nulls before storing `intro`/`config` leaves. `$this->form->getRawState()['spike_content']` is available in `afterSave` even though the field is `dehydrated(false)`, and it carries the current block order.

**0.1b Parent-record fallback confirmed.** On mount-time hydration (the path that loads item rows into blocks), a block sub-field named after a real `CurriculumSession` attribute (`summary` in the probe) is overwritten with the *parent record's* translations by `TranslatableComboField::formatStateUsing`; `body` is untouched. The test helper's `fillForm()` does not show this because it writes Livewire state directly and skips hydration hooks, so do not rely on `fillForm`-based tests to catch it. Phase 2 must therefore either (preferred) add an explicit opt-out to `TranslatableComboField` (e.g. `->fromRecord(false)` that skips the record lookup) and use it on every block field, or keep every block sub-field name off the session's attribute list (`title`, `summary`, `description`, `slug`, `builds_toward`, `order_column`). The opt-out is the safer choice because `intro` is a real column on the *item* table and the plan names it as a block field.

**0.1c Content driver.** `EditCurriculumSession` uses the resource-level lara-zeus concern, which has no `HasActiveLocaleSwitcher`, so `getFilamentTranslatableContentDriver()` already returns `null` on this page. Saving with blocks present left the session's own translatables intact. Nothing to null out; keep it that way (do not switch the page to the `EditRecord` concern).

**0.2 Block identity.** Filament 5 keys Builder items by a uuid from `CanGenerateUuids::generateUuid()`. Within one request cycle the uuid is stable through the `reorder` action (`callFormComponentAction('spike_content', 'reorder', arguments: ['items' => [...]])` preserved keys and reordered data) and is visible in `afterSave`. But `Builder::hydrateItems()` regenerates every uuid on each hydration, so uuids differ between page loads and cannot be the row identity. Use `Hidden::make('key')->default(fn () => Str::uuid())` in every block (as the module outcomes Repeater already does); the Builder uuid is only for intra-request drag/reorder. `generateUuidUsing()` receives no item data, so it cannot be used to derive the uuid from the row key.
