# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Work Patterns

Follow the plan / change-log / code-review / issue conventions in [.claude/work-patterns.md](.claude/work-patterns.md). Summary: accepted plans go in `docs/plans/` with a **Status:** line, finished work gets a `docs/change-logs/` entry, reviews go in `docs/code-reviews/`, captured issues go in `docs/issues/` plus a GitHub issue.

## What this is

The **Digital Sovereignty Library**: a public online learning course plus a searchable resource library, built for smallholder-farming / agroecology audiences. It was forked from Stats4SD's white-label "resources site template" (itself a spin-off of `stats4sd-resources`), and the template's generic library machinery (Troves, Collections, Tags, drafts, search, admin panel) is still the foundation. On top of that sits a **curriculum layer**: an onboarding intro, a five-node learning map, a four-pillar "sovereign toolkit", and a site-wide glossary, all seeded with content in English plus 11 translations.

Two consequences for how you work here:

- Course content **enters through seeders and is then edited in the admin**. `CurriculumModule`, `CurriculumSession` and `CurriculumSessionItem` rows are created by `CurriculumSeeder` from the YAML under `database/curriculum/` (`firstOrCreate`, so admin edits survive a re-seed). Admins can create and delete learning-map modules and author session content with a Builder; the intro module and toolkit pillars are fixed by `key` (the toolkit grid and the pillar tags are keyed on them), so adding a pillar still means changing the YAML *and* the Blade layout.
- The template's `.env`/config-driven branding is still in place, but this repo is no longer trying to stay generic. Don't reintroduce upstream org-specific concepts (Hubs, Organisations, per-org theming), and don't remove the curriculum layer to "get back to the template".

## Stack

- **Laravel 13** + **PHP 8.3+** (CI runs 8.4)
- **Filament 5** — admin panel at `/admin`. Translatable admin UI uses `lara-zeus/spatie-translatable`.
- **Livewire 4** — the public browse/search surface (`BrowseAll`) and the small related-items components
- **Alpine.js** + **Tailwind CSS 4** + **DaisyUI 5** + **Vite** — frontend. There is **no `resources/js/`**; Vite only builds CSS. All interactivity is Alpine (inline in Blade) or Livewire. Vue is listed in `package.json` but unused.
- **Meilisearch** via Laravel Scout — full-text search, with a database fallback (see Search)
- **Spatie Media Library** — per-locale file/media management
- **Spatie Translatable** — multilingual content (JSON columns; locales configured per-site at runtime)
- **Translation.io** (`tio/laravel`, gettext mode) — UI string translation via the `t('...')` helper; compiled `.po`/`.mo` files live in `lang/gettext/<locale>/`
- **MySQL** — primary database. Tests run on SQLite `:memory:`, but a couple of code paths are MySQL-specific (`whereJsonContains` on `previous_slugs`; `EditTrove::isDuplicateDraftViolation()` keys on MySQL errno `1062`).
- **spatie/laravel-permission** v8 — three fixed roles (`viewer`/`editor`/`admin`)
- **parallax/filament-comments** — admin-panel comments on records, policy-gated via `FilamentCommentPolicy`
- Installed but **not wired**: `chrisreedio/socialment` (Azure AD login), `awcodes/shout`. Sentry and Telescope are configured.
- Drafting is fully app-owned. There are no `packages/` submodules and no drafts package; the only third-party Filament/Scout glue is `kainiklas/filament-scout`.

## Commands

```bash
# Frontend (CSS only)
npm run dev
npm run build

# Backend
php artisan migrate
php artisan migrate --seed          # roles, tag types, trove types, site content/settings, curriculum modules + glossary, toolkit tools; in local env also TestSeeder (test@example.com / password)
php artisan app:fresh               # migrate:fresh --seed, then the example troves/collections seeder
php artisan db:seed --class="Database\Seeders\Example\ExampleDataSeeder"   # example troves/collections/tags only
php artisan scout:sync-index-settings                         # push index-settings from config/scout.php after schema changes
php artisan scout:import "App\Models\Trove"                   # reindex (also: App\Models\Collection)
php artisan user:set-role user@example.com admin              # viewer|editor|admin; --force to demote the last admin
php artisan troves:import list.csv --uploader=a@b.org --publish --dry-run  # bulk CSV import (format: docs/import/README.md)
php artisan translation:sync                                  # push/pull UI strings to Translation.io (needs TRANSLATIONIO_KEY)

# Tests — Pest 4 on SQLite :memory: (phpunit.xml). Three suites: Unit, Feature, Integration.
php artisan test --testsuite=Unit,Feature                     # what CI runs; Integration needs a real Meilisearch
MEILISEARCH_INTEGRATION=1 php artisan test --testsuite=Integration
php artisan test --filter=TestName

# Local services
meilisearch --master-key="aSampleMasterKey"                   # only needed when SCOUT_DRIVER=meilisearch
php artisan queue:work                                        # SCOUT_QUEUE=true by default
```

Test-harness gotchas (helpers in `tests/Pest.php`): `actingAsAdmin()/actingAsEditor()/actingAsViewer()` assign roles (UserFactory has matching states, and a global `beforeEach` resets spatie's permission cache); `publishedTrove()`/`draftTrove()` build lifecycle fixtures; `PublishedScope` self-disables in tests because Filament registers the default panel as "current" at boot, so call `usePublicContext()` to exercise public visibility; the public layout needs `config('branding.locales')`, so use `bootPublicSite()`.

Pint is a dev dependency with no config file (`vendor/bin/pint`). Rector is installed but has no `rector.php`.

## Branding & locales

- `BRAND_ORG_NAME`, `BRAND_HOME_URL`, `BRAND_LINKEDIN_URL`, `BRAND_YOUTUBE_URL` are read via `config/branding.php`.
- Colours/font are CSS variables under `:root` in `resources/css/app.css` (`--brand-primary`, `--brand-secondary`, `--brand-bg`, `--brand-footer-bg`, `--brand-footer-text`, `--brand-font`, plus `--brand-success/info/warning`). `AdminPanelProvider::brandPrimary()` regex-scrapes `--brand-primary` out of `app.css` at runtime to colour the Filament panel.
- Logo (`public/images/logo.png`) and banner (`public/images/banner.png`) are optional; views hide them if the file is missing.
- **Locales are runtime data, not config.** They're managed in the admin panel ("Site Options" → `SiteSetting.locales`) and hydrated into `config('branding.locales')` and the legacy `config('app.locales')` by `AppServiceProvider::boot()` (try/catch for fresh installs; static fallback `['en' => 'English']`). Prefer `config('branding.locales', ...)` in new code. `AppServiceProvider` also hydrates `config('branding.features.show_trove_type_filter')` from `SiteSetting`.
- **Right-to-left**: `config('branding.rtl_locales')` (`ar`, `he`, `fa`, `ur`) drives `<html dir="rtl">` in `layouts/app.blade.php` (prefix-aware, so `ar_EG` matches `ar`). Use Tailwind `rtl:` variants and logical spacing (`ms-`/`me-`), and the `<x-dir-arrow>` component for arrows that must mirror. `tests/Feature/Http/LayoutDirectionTest.php` covers this.
- Seeded content locales: `en` plus `ar`, `es`, `fil`, `fr`, `hi`, `id`, `it`, `pt`, `ru`, `sw`, `zh_CN`. The same list has gettext catalogues under `lang/gettext/`.

## Architecture

### Core entities

| Model | Purpose |
|---|---|
| `Trove` | A single learning resource (document, video, link, tool, etc.) |
| `Collection` | A curated group of Troves |
| `TroveType` | Resource type classification |
| `Tag` + `TagType` | Flexible tagging taxonomy, polymorphic (`morphToMany`) onto Trove. The `tools` TagType with pillar tags is seeded by `ToolkitToolsSeeder`. |
| `CurriculumModule` | One block of the course, single-table-inherited by `section` into `IntroModule` / `MapModule` / `ToolkitModule` (hand-rolled `newFromBuilder`, not `parental`; every row is a child). Immutable `key`, translatable `title/subtitle/description/note/goal`, structured `learning_outcomes` (list of `{key, statement, in_practice}`). Map modules have `number` and ordered `sessions`; intro/toolkit modules have an ordered `troves` pivot. |
| `CurriculumSession` | An ordered stop in a map module (`slug` immutable after create, `builds_toward` → an outcome key). Its content is `items`. |
| `CurriculumSessionItem` | One typed content block on a session (`App\Enums\CurriculumItemType`: `prose`, `callout`, `note_prompt`, `note_canvas`, `note_matrix`, `quiz`, `trove`), ordered by `position`, with a uuid `key` that is immutable because it namespaces learners' browser-side notes. `config` JSON shape per type lives in `app/Curriculum/Items/*` (`ItemDefinition`, `ItemRegistry`); trove items use `trove_id` + translatable `intro` instead. |
| `GlossaryTerm` | Translatable `term` + `definition`; no relations. Embedded as JSON on every public page for client-side lookup. |
| `SiteSetting` | Singleton (`SiteSetting::instance()`) holding `show_language_filter`, `show_trove_type_filter`, `open_registration`, `locales` |
| `SiteContent` | Key/value translatable CMS strings, `SiteContent::get($key)` |
| `Invite` / `PasswordSetup` | Onboarding tokens (64 chars, 7-day expiry); see Authentication |

There is no `Organisation` or `Hub` model.

### Curriculum layer

- **Routes** (`routes/web.php`, all closures): `/home` renders `home_digital_sovereignty.blade.php` (landing + Alpine step machine into the intro module, deep-linkable via `#intro`); `/curriculum` renders the stepper, learning map and toolkit sections; `/curriculum/{key}` is a map-node detail page (404s for toolkit keys); `/toolkit/{key}` is a pillar detail page. There is no standalone toolkit index.
- **Views**: `resources/views/curriculum/{index,module,pillar}.blade.php` plus `partials/map.blade.php` (SVG learning map: a fixed hand-drawn trail from `App\Support\Curriculum\MapLayout::trail()`, with nodes spaced evenly along its two rows by `MapLayout::nodes(N)` so adding or deleting a map module reshuffles them; plus a mobile list fallback) and `partials/toolkit.blade.php` (featured "Farm Hack Box" card + 2×2 pillar grid with inline SVG icons). `<x-curriculum-resource-row>` is the shared trove card.
- **Content** lives as YAML in `database/curriculum/` (see its `README.md`): `modules/<key>.yaml` (module fields, outcomes, sessions and their `items`), `glossary.yaml`, `toolkit/tags.yaml` and `toolkit/tools/<pillar>.yaml`, every translatable field a `locale => value` map. `Database\Seeders\Prep\CurriculumSeeder` and `ToolkitToolsSeeder` load them; seeded item `config` goes through the same `ItemDefinition::normalise()` as admin input, so bad YAML fails the seed. Both are idempotent (`firstOrCreate` on `key`/`slug`/English title), so `db:seed` never overwrites admin edits. Module 3 (`community-needs`) is the only module with sessions and items so far; items are English-only.
- **Session pages** (`/curriculum/{module}/{session}`, `curriculum/session.blade.php`) render `items` through `<x-curriculum-item :item>` → `curriculum/items/<type>.blade.php`; a trove item whose trove is unpublished renders nothing. Learner answers never reach the server: `<x-learner-store>` keeps an Alpine store per session in `localStorage` (`dsl:v1:{module.key}:{session.slug}`), and the quiz scores itself in the browser from a JSON payload embedded in the page.
- **Admin content editing**: `EditCurriculumSession` has a "Content" tab with a Filament `Builder` (`App\Filament\Curriculum\SessionContentBuilder`, one block per `ItemDefinition`); `App\Services\CurriculumSessionContentSync` maps blocks to item rows by `key` in a transaction. Block sub-fields use `TranslatableComboField::fromRecord(false)`; never name one after a real model attribute.
- **Learning outcomes** are a structured list; `CurriculumModule::outcomes_list` resolves the current locale with fallback.
- **Glossary**: `<x-glossary-drawer>` (included in the layout) embeds all terms as `<script type="application/json" id="glossary-data">`, walks every `[data-glossary-scope]` element to wrap matching text in `.glossary-term` spans, and opens a searchable side drawer on click or on the `open-glossary` window event (header button). Wrap any new rich-text region in `data-glossary-scope` if terms should be highlighted there.
- **Admin**: `CurriculumModuleResource` lists all sections; "New map module" creates a `MapModule` with a generated immutable `key` and `number = max + 1`, and only map modules can be deleted (`CurriculumModulePolicy`, registered explicitly in `AuthServiceProvider` because the Gate does not walk parent classes for auto-discovered policies). `SessionsRelationManager` creates/reorders/deletes sessions and links to `CurriculumSessionResource` for editing; `TrovesRelationManager` (intro/toolkit modules) attaches only published canonicals. `GlossaryTermResource` is simple CRUD via modals. All under the "Curriculum" nav group; mutations need `canEdit()`.
- **Tests**: `tests/Feature/Http/CurriculumTest.php`, `CurriculumSessionItemsTest.php`, `GlossaryTest.php`, `LayoutDirectionTest.php`, `tests/Feature/Filament/CurriculumModuleResourceTest.php`, `CurriculumSessionResourceTest.php`, `CurriculumSessionContentTest.php`, `GlossaryTermResourceTest.php`, `tests/Feature/Models/CurriculumModuleInheritanceTest.php`, `tests/Feature/Seeders/*`, `tests/Unit/Curriculum/ItemDefinitionsTest.php`, `tests/Unit/Services/CurriculumSessionContentSyncTest.php`, `tests/Unit/Support/MapLayoutTest.php`.

### Key patterns

**Multilingual content**: `Trove`, `Collection`, `CurriculumModule`, `GlossaryTerm` and the taxonomy models use `HasTranslations` (Spatie): JSON columns keyed by locale. The `set.locale` middleware wraps all web routes; the language switcher is `?locale=xx`. UI chrome strings go through `t('...')` (Translation.io gettext), not `__()`.

**Draft/versioning (app-owned shadow-draft/canonical model)**: Each logical Trove is one **canonical** row plus at most one **shadow-draft** row (`published_id → canonical.id`, `unique(published_id)`). `published_at` is the sole source of truth for "published". Two derived state axes: `Trove::publicationState()` → `App\Enums\PublicationState` (Draft / Published / PendingChanges) and `Trove::reviewState()` → `App\Enums\ReviewState` (None / InReview / Reviewed), each with a DB-mirror scope (`scopeWithPublicationState`, `scopeWithReviewState`) that must stay in parity with the accessor (`tests/Feature/Models/TroveStateParityTest.php` locks this).

`App\Services\TrovePublisher` is the ONLY place that mutates the lifecycle (`draftFor`, `publish`, `discardDraft`, `delete`, `unpublish`, `requestReview`, `completeReview`). `$draftableRelations` (`tags`, `collections`) is copied between canonical and draft; media is copied via `Media::copy()`, superseded media is renamed out of its collection in-transaction and deleted on disk after commit (`app:prune-superseded-media` sweeps up failures daily).

`App\Models\Scopes\PublishedScope` (global on `Trove`) restricts public/console/queue/Scout reads to published canonicals but **self-disables whenever a Filament panel is the current request context**. Preview route `/resources/preview/{slug}` (auth) loads `Trove::withDrafts()->workingVersions()`; public `/resources/{troveKey}` uses `Trove::findBySlugOrRedirect()` which falls back through `previous_slugs` before 404ing.

The Filament seam is `app/Filament/Resources/TroveResource/Pages/EditTrove.php`: saving a *live* trove forks a shadow draft (`forkToDraftAndRebind` + `remapMediaState`) so edits land on the draft.

**Search**: `App\Contracts\SearchesLibrary` is the contract, bound in `AppServiceProvider` to `App\Services\Search\MeilisearchLibrarySearch` when `SCOUT_DRIVER=meilisearch`, else `DatabaseLibrarySearch`. Request/result DTOs (`LibrarySearchRequest`, `LibrarySearchResult`, `LibraryHit`, `LibraryFacets`) live alongside. `BrowseAll` (`app/Livewire/BrowseAll.php`) is the single public search/browse page: federated Trove + Collection hits ranked by score, tag / trove-type / language filters with facet counts, manual pagination (`perPage` 24). If the engine is unreachable it degrades to the database search with a "search unavailable" notice. `toSearchableArray()` flattens all locale variants into single strings; index settings (filterable/sortable attributes) are declared per model in `config/scout.php` `index-settings`. After changing either, run `scout:sync-index-settings` then `scout:import`. Scout runs `after_commit => true`, driver defaults to `null`, and `app/Console/Kernel.php` schedules a nightly `scout:import` reconciliation when the driver is meilisearch. `tests/Feature/Search/ReindexTriggersTest.php` covers the tag/collection-membership reindex hooks; `tests/Integration/Search/RealMeilisearchTest.php` runs against a real engine in CI.

**Video links**: `Trove.video_links` (translatable array; the column has always been `video_links` on this fork, the template's `youtube_links` rename was folded into the create migration) holds any video share URL. `App\Services\VideoLinkResolver` dispatches to adapters in `app/Services/VideoLink/` (`YouTubeAdapter`, `EcoAgTubeAdapter`, `GenericVideoAdapter` via `embed/embed`) implementing `App\Contracts\ResolvesVideoLinks`; resolution failures never block a trove save, and non-embeddable links render as a link card (`<x-video-link>`). Design spec: `docs/specs/2026-07-08-video-embedding-design.md`.

**Media**: Per-locale media collections registered from the configured locales (`cover_image_en`, `content_en`, …). Cover images have a `cover_thumb` (450px) conversion. `Collection::coverImage/coverImageThumb` and `Trove::coverImageThumb/getCoverImageUrl()` fall back current locale → other locales → static default. Storage disk is `FILESYSTEM_DISK`/`MEDIA_DISK` (S3 by default; `local`/`public` for dev without S3).

**Admin panel**: Filament resources in `app/Filament/Resources/`: Trove, Collection, CurriculumModule, GlossaryTerm, TroveType, Tag, TagType, plus admin-only User and Invite. Navigation is built explicitly in the panel provider: Troves and Collections as top-level items, then groups Curriculum, Details (types/tags), Site Settings, Users. Panel config lives in `app/Providers/Filament/AdminPanelProvider.php` (only the lara-zeus `SpatieTranslatablePlugin` is registered). Custom pages `SiteOptionsPage` and `SiteContentPage` (`canAccess() → isAdmin()`) manage `SiteSetting`/`SiteContent`.

**Filament 5 notes**: forms/infolists are `Filament\Schemas\Schema` (`form(Schema $schema): Schema`); layout components live in `Filament\Schemas\Components\*`, inputs in `Filament\Forms\Components\*`; actions are unified under `Filament\Actions\*`; tables use `->recordActions()`/`->toolbarActions()`; custom pages render via a `content(Schema)` override. Two v5 defaults are deliberately overridden: `->visibility('public')` on every `SpatieMediaLibraryFileUpload` (v5 defaults to `private` on non-local disks → 403s) and `->deferFilters(false)` on tables expecting instant filtering. Gotcha: with the app's `TranslatableComboField` (whole locale dictionary as form state), edit pages must use the lara-zeus **resource-level** `Resources\Concerns\Translatable` concern (as `EditTagType` and `EditCurriculumModule` do); the page-level `EditRecord` concern calls `setTranslation()` per attribute and silently nests the JSON.

**Frontend**: Blade + Alpine. `header.blade.php` carries the nav (Home, Curriculum, Browse Library, glossary button, language dropdown). Public Livewire components besides `BrowseAll`: `CollectionTroves`, `TroveCollections`, `TroveRelatedTroves`. `AllTrovesTable` is an admin-side Filament table embedded in `ViewCollection`. `home.blade.php` is the template's original home page and is no longer routed.

### Directory layout

```
app/
  Contracts/          # SearchesLibrary, ResolvesVideoLinks
  Enums/              # PublicationState, ReviewState, UserRole, InviteStatus
  Filament/
    Resources/        # Trove, Collection, CurriculumModule, GlossaryTerm, TroveType, Tag, TagType, User, Invite
    Pages/            # SiteOptionsPage, SiteContentPage, Login; Auth/ has Register + SetPassword
    Translatable/     # TranslatableComboField and friends
  Livewire/           # BrowseAll (public search), related-items components, AllTrovesTable (admin)
  Mail/               # UserInviteMail, SetPasswordMail
  Models/             # + Scopes/PublishedScope
  Policies/           # content policies + admin-only User/Invite policies + FilamentCommentPolicy
  Services/           # TrovePublisher, VideoLinkResolver, Search/*, VideoLink/*
  Console/Commands/   # ImportTroves, SetUserRole, PruneSupersededMedia, FreshWithExampleData, PurgeTelescopeEntries
resources/
  views/              # home_digital_sovereignty, trove, collection, curriculum/, components/, livewire/, layouts/
  css/app.css         # Tailwind entry + brand CSS variables
lang/gettext/         # Translation.io catalogues per locale
routes/web.php        # all public routes, under set.locale
database/
  curriculum/         # course content as YAML (modules + sessions + items, glossary, toolkit), read by the seeders
  seeders/Prep/       # base seed incl. CurriculumSeeder, ToolkitToolsSeeder
  seeders/Example/    # optional example troves/collections
docs/
  plans/ change-logs/ code-reviews/ issues/   # see .claude/work-patterns.md
  specs/              # design specs written before plans
  import/             # CSV import format + templates
```

### Authentication & roles

- Standard Laravel Auth. The panel has `->registration(Register::class)` and `->passwordReset()` enabled. Azure AD (`socialment`) is installed but not registered in the panel.
- **Roles**: `viewer` / `editor` / `admin`, typed via `App\Enums\UserRole` (never magic strings). `RoleSeeder` creates them idempotently. `User` helpers: `isAdmin()`, `canEdit()` (editor or admin), `isLastAdmin()`.
- `User::canAccessPanel()` returns `true` for all authenticated users **by design**: viewers get a read-only panel and capability is governed by policies. Content policies: everyone may view, mutating abilities require `canEdit()`. `UserPolicy`/`InvitePolicy` are admin-only; `UserPolicy::delete()` blocks self-deletion and deleting the last admin.
- **Onboarding flows** (`docs/change-logs/user-management-and-invites.md`, `user-set-password-on-create.md`): admin `Invite` → tokenised `/admin/register`; open registration (off by default, `SiteSetting::open_registration`) grants `viewer`; admin-created users get a single-use `PasswordSetup` link to `/admin/set-password`. `MAIL_*` must be configured for any of these.
- `AppServiceProvider::boot()` calls `Model::unguard()` globally.
