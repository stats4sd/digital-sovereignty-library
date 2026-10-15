# Plan: Trove import wizard (admin UI)

**Status:** Not Started

## Goal

Give non-technical editors a guided, in-browser way to do what `php artisan troves:import` does today: upload a CSV or Excel file, see exactly what will be imported, resolve the decisions the CLI currently makes silently (new tags, duplicates), attach files and cover images per trove, and run the import. The CLI stays, and both share one import engine so behaviour cannot drift.

## What changes vs the CLI

| Concern | CLI today (`ImportTroves`) | Wizard |
|---|---|---|
| File format | CSV only | CSV, `.xlsx` (first sheet) |
| Uploader | `--uploader=email` | The logged-in user |
| Unknown tag names | Always created | User chooses per tag: create new, or map to an existing tag of the same type |
| Unknown tag types (`tag:<slug>` columns) | Abort, or create with `--create-tag-types` | User chooses: create tag type, map to an existing tag type, or ignore the column |
| New collections | Always created | Listed for confirmation: create, or map to an existing collection |
| Duplicates by content (`link_url`, video source) | Silently skipped | Shown with the matching trove; user chooses skip (default) or import anyway |
| Duplicates by title | Not detected | New check; shown with the matching trove; user chooses import anyway (default) or skip |
| Row errors | Abort everything | Listed with line numbers; user either fixes the file and re-uploads, or ticks "import the valid rows only" |
| Content files | Not supported | Per-trove upload into `content_<locale>` |
| Cover image | `cover_image_url` download only | Per-trove upload into `cover_image_<locale>`; overrides any `cover_image_url` in the file |
| Search index | Disabled, user told to run `scout:import` | Disabled during the write, then the created troves/collections are made searchable automatically (queued, `SCOUT_QUEUE=true`) |

## Design

### 1. Extract a shared import engine

Move the logic out of `app/Console/Commands/ImportTroves.php` into `app/Services/TroveImport/`, keeping the command as a thin adapter. The existing `tests/Feature/Console/ImportTrovesCommandTest.php` is the safety net for this step and must pass unchanged.

- `SpreadsheetReader` — `read(string $path): ImportSheet` (header + `[line, row]` list). CSV via `fgetcsv` (current BOM handling); `.xlsx` via OpenSpout, reading the first sheet only. OpenSpout is currently only a transitive Filament dependency, so add `openspout/openspout` to `composer.json` explicitly. Excel cells are normalised to strings: `DateTimeInterface` → `Y-m-d`, numbers without trailing `.0`, booleans/nulls → `''`. Line numbers stay 1-based including the header so they match what the user sees in Excel.
- `ImportPlanner` — today's `parseHeader()`, `buildIndexes()`, `buildPlan()` and `localizedColumnValues()`, returning a structured `ImportPlan` instead of strings, so the UI can render it and the CLI can print it:
  - `headerErrors: list<string>`
  - `unknownTagTypes: list<string>` (slugs)
  - `newTags: array<slug, array<lower, name>>`, `newCollections: array<lower, title>`
  - `rows: list<PlannedRow>` — each with `line`, the current planned fields, `errors: list<string>`, and `matches: list<TroveMatch>`
  - `TroveMatch { kind: 'content'|'title', key: string, troveId: ?int, inFile: ?int /* earlier line */ }`
- The planner **detects** duplicates but no longer decides; it records matches. Title matching is new: normalise (trim, collapse whitespace, `mb_strtolower`) every title translation of every row and compare against every title translation of existing troves (`Trove::withDrafts()->withTrashed()`) and earlier rows in the file. Matches against a shadow draft resolve to its canonical (`published_id ?? id`), so the UI links to one logical trove. Trashed matches are flagged as such.
- `ImportDecisions` — value object holding the user's choices: `tagTypeMap` (slug → `create` | existing tag type id | `ignore`), `tagMap` (slug + lower name → `create` | existing tag id), `collectionMap` (lower → `create` | existing collection id), `rowActions` (line → `import` | `skip`), `skipErrorRows: bool`, `publish: bool`, and per-row media (`locale`, cover image path, content file paths).
  - `ImportDecisions::defaultsFor(ImportPlan)` reproduces CLI behaviour: create everything, skip content matches, import title matches. The command calls this (plus `--create-tag-types` / `--publish`), so the CLI keeps its current semantics; it additionally prints title matches as warnings.
- `ImportExecutor` — today's `executePlan()` + video resolution + `downloadCoverImages()`, applying `ImportDecisions`: mapped tags/collections link to existing IDs instead of creating; ignored tag-type columns are dropped; skipped/error rows are excluded. Same single transaction, `withoutSyncingToSearch`, `TrovePublisher::publish()` when publishing. After commit: attach uploaded media (`addMedia($path)->toMediaCollection("cover_image_{$locale}")` / `"content_{$locale}"`), then download `cover_image_url`s for rows without an uploaded cover; failures become warnings on the `ImportResult`, never a rollback. Then call `->searchable()` on the created troves and touched collections. Returns `ImportResult { created: list<Trove>, skipped, warnings }`.

### 2. Wizard page

New Filament page `app/Filament/Pages/ImportTroves.php` (pattern: `SiteOptionsPage` — `Page` + `HasForms`, `content(Schema)` override), containing one `Filament\Schemas\Components\Wizard`. `canAccess()` → `auth()->user()->canEdit()` (same gate as `TrovePolicy::create()`). Reached from a header action "Import from spreadsheet" on the Troves list page and from a nav item under Troves in `AdminPanelProvider`.

State is plain arrays on the Livewire component (the planner output serialised via `toArray()`), never models. The uploaded spreadsheet is moved from Livewire's temporary storage to `storage/app/imports/{uuid}/` on the local disk so the plan can be rebuilt at execution time; the directory is deleted after a successful import, and a daily scheduled sweep removes ones older than 24h.

**Step 1 — Upload**

- `FileUpload` accepting `.csv` / `.xlsx` (mime + extension), single file, local disk, size cap (e.g. 10 MB).
- Short plain-language instructions, a link to download the CSV and Excel templates (serve `docs/import/*.csv` via a small authenticated route, plus a new `trove-import-template.xlsx` generated from the CSV), and a collapsible column reference rendered from the same source as `docs/import/README.md`.
- "Next" runs the reader + planner. Header errors (unknown column, unconfigured locale, flat + suffixed clash, no title column) are shown inline and block the step — they mean the file is wrong, not a row. Also block on zero data rows and on more than N rows (start with 200; see Risks).

**Step 2 — Review**

Sections, each only rendered if it has content; a count badge on each heading.

1. **Summary bar**: N troves to import, N need a decision, N errors.
2. **Rows with errors**: table of line + messages. Below it, a toggle "Import the valid rows only" (off by default). With it off, "Next" is blocked and the message tells the user to fix the file and go back to step 1.
3. **New tag types** (only for unknown `tag:<slug>` columns): per slug, a select: "Create tag type '<Headline>'" / existing tag type / "Ignore this column".
4. **New tags**, grouped by tag type: per tag, a select defaulting to "Create new tag '<name>'", with the type's existing tags as options. Pre-suggest a close existing match (case/accents/plural-insensitive, `similar_text` ≥ 85%) as a hint next to the field, not as the default — silent auto-mapping is what the user wants to avoid. Tags under a tag type the user chose to map are re-grouped under the mapped type; under an ignored type they disappear.
5. **New collections**: same create-or-map select.
6. **Possible duplicates**: one card per row with matches, showing the row's title and source next to each matching trove (title, state badge from `publicationState()`, "deleted" if trashed, link to its edit page in a new tab), with a radio "Skip this row" / "Import as a new trove". Defaults: content match → skip, title-only match → import. Matches against earlier rows in the same file are shown the same way, naming the line.
7. **Preview**: read-only table of the rows that will be imported (title in primary locale, other locales as badges, type, creation date, link/video, tags, collections), reflecting the decisions above live.

**Step 3 — Files** (one block per row being imported)

- Not in the original brief's two tabs: per-trove uploads are a different task from reviewing the file and would make step 2 very long, so they get their own step.
- Fixed, non-addable repeater, one item per importable row, collapsed by default, headed with the row's primary title.
- Fields per item: locale select (options: the row's locales that have a title; default primary locale) applying to both uploads; `SpatieMediaLibraryFileUpload`-equivalent plain `FileUpload` for cover image (image, single) and content files (multiple), both on the local temp disk — media is attached by the executor after the trove exists. Hint text "Will be downloaded from <url> unless you upload one" when the file has a `cover_image_url`.
- Everything optional; a "skip files for now" note explains they can be added later from the trove's edit page.

**Step 4 — Import**

- Final summary (counts of troves, new tags/tag types/collections, mapped items, skipped rows, files to attach).
- Toggle "Publish immediately" (default off → drafts for review, as the CLI).
- "Import" button: rebuild the plan from the stored file, re-validate decisions against it (a mapped tag/collection/trove may have been deleted or a new duplicate created since step 2). If anything changed, return to step 2 with a notice instead of importing. Otherwise run the executor with `set_time_limit` raised.
- Result screen: created troves listed with links to their edit pages, warnings (failed cover downloads, unattached files), and a "Start another import" action.

### 3. Docs

Update `docs/import/README.md` (Excel support, the wizard as the primary route, title-duplicate warnings in the CLI) and the CLAUDE.md admin/commands sections.

## Deliverables (in order)

1. `composer require openspout/openspout`; `SpreadsheetReader` + unit tests (CSV with BOM, xlsx with dates/numbers/empty rows).
2. Extract `ImportPlanner` / `ImportDecisions` / `ImportExecutor`; slim `ImportTroves` command onto them. Existing command tests pass unchanged; add title-match and xlsx tests to them.
3. Decision application in the executor (tag/tag-type/collection mapping, row skip/import, skip error rows) + unit tests for each mapping, including "map tag to an existing tag in a type the user also mapped".
4. Media attachment + post-commit `searchable()` in the executor, with tests using `Storage::fake` and `Http::fake`.
5. Wizard page steps 1–2 + Livewire tests (`Livewire::test(ImportTroves::class)`): upload validation, header-error blocking, error-row toggle, decision defaults, viewer cannot access.
6. Steps 3–4 + tests: per-row uploads land in the right `content_<locale>` / `cover_image_<locale>` collection, publish toggle, stale-plan detection sends the user back to step 2.
7. Template download route + xlsx template; temp-import cleanup schedule.
8. Docs update; change log in `docs/change-logs/`.

## Risks / constraints

- **Request duration**: video resolution is one HTTP call per video URL, plus cover downloads, all synchronous. The 200-row cap keeps this bounded; moving execution into a queued job with a progress poll is the follow-up if real files are larger. A job can't use Livewire temp files, so the per-row uploads would need moving to `storage/app/imports/{uuid}/` first — the plan already does this for the spreadsheet, so do it for media too to keep that option open.
- **Upload limits**: check `config/livewire.php` `temporary_file_upload` rules and the server's `upload_max_filesize` / `post_max_size` against expected content file sizes (PDFs, slide decks); multiple content files per row across many rows can be large in one session.
- **Livewire payload size**: a 200-row plan with matches held as component state is sizeable; keep heavy, non-interactive data (preview rows) out of public properties where possible and recompute from the stored file.
- **Title matching false positives**: short generic titles ("Introduction") will match. Defaulting title-only matches to "import" keeps this a warning rather than a blocker.
- **`whereJsonContains`/JSON matching** stays in PHP (as today) so SQLite tests and MySQL behave the same.

## Open questions

- Should uploading content files set `source` to internal? Today `source` is always `false` on import.
- Is mapping tag types (vs only create/ignore) needed in v1, or is it rarely used enough to drop?
- Should an "update the existing trove" action exist for duplicates? Out of scope here: it would have to go through `TrovePublisher::draftFor()` and decide which fields win.

### Future work (out of scope)

- Queued execution with progress for large files.
- Column-mapping step for spreadsheets whose headers don't follow the template.
- Import history / audit log of past imports.
