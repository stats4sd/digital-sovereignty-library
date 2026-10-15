# Search Milestone 1: federated Meilisearch search and the BrowseAll rewrite

**Date**: 2026-07-15 – 2026-09-21
**Branch**: `search-review` (Phase 1, one commit per task), then `dev`
**Plan**: [docs/archive/2026-07-15-search-milestone-1-federated-browse.md](../archive/2026-07-15-search-milestone-1-federated-browse.md)
**Review**: [docs/code-reviews/search-and-browse-review.md](../code-reviews/search-and-browse-review.md) (§3 is the design implemented here)

Implements the plan above. `BrowseAll` no longer searches, then filters, then merges in PHP. It sends one federated Meilisearch request that does text search, tag/type/language filtering, facet counts, ranking across both indexes and pagination, then hydrates a single page of models. When the engine is missing or down, a portable database fallback serves the page. The whole render path now runs on SQLite. Task 9 (Scout 11 upgrade) was deferred, see below and [issue #4](https://github.com/stats4sd/digital-sovereignty-library/issues/4).

## Phase 1: rewrite

- **Index documents** (`6b032f3`): `Trove::toSearchableArray()` adds `tag_ids`, searchable `tag_names` (flattened across locales), `trove_type_ids`, `locales` (locales with a non-empty title) and `sort_date` (`published_at`). It drops `is_published`. `Collection::toSearchableArray()` aggregates `tag_ids`/`trove_type_ids` from published canonical members only, with an explicit `published_at`/`published_id` constraint because `PublishedScope` self-disables in panel context. It also gets `locales`, and its `sort_date` comes from `created_at`. Filterable, sortable and searchable attributes are declared in `config/scout.php` `index-settings`.
- **Search contract** (`81f24a7`): `App\Contracts\SearchesLibrary` with readonly DTOs (`LibrarySearchRequest`, `LibrarySearchResult`, `LibraryHit`, `LibraryFacets`) and `SearchUnavailableException`.
  - `MeilisearchLibrarySearch` sends one `multiSearch()` with a `MultiSearchFederation`. Both indexes share the filter expression: OR within a tag type, AND across types. A blank query sorts by `sort_date:desc`. Facets are merged across indexes. Pagination is set at the federation level with `limit`/`offset`, and `totalPages` is derived from `estimatedTotalHits`.
  - `DatabaseLibrarySearch` is the portable-SQL fallback: date-ordered, with no facets.
  - The container binds by `scout.driver`. `UsesCustomSearchOptions` was deleted.
- **BrowseAll** (`283e65a`): a single full-page component that holds only scalar query/filter/page state, all of it `#[Url]`. It sends one search per render and hydrates hits in order. An engine outage sets `searchUnavailable` and re-runs the search through the database fallback. Filter tag types are sorted in PHP, so the MySQL-only `ISNULL`/`JSON_EXTRACT` ordering is gone. `SearchBar` was folded in and deleted.
- **Blade** (`3860059`): a new resource-type filter group, and facet count badges on every tag/type/language checkbox. Pagination is now windowed First/Prev/…/Next/Last buttons with `aria-current` and real `disabled` attributes, replacing the Alpine `entangle` machinery. Card images get alt text, and the clear control is a real button.
- **Reindex triggers** (`2001660`): tag and membership data now live in the index, so changes made outside a trove save have to reindex.
  - New `TagObserver`: a tag rename or delete reindexes that tag's published troves and their collections. Member ids are captured before the pivot cascade.
  - Collections are reindexed on attach/detach/bulk-attach in `AllTrovesTable` and on detach in `TrovesRelationManager`.
  - `TrovePublisher` reindexes the affected collections after commit on publish (both sides of a membership change), unpublish and delete.
- **Cleanup** (`52aa9a7`): removed `Trove::themeAndTopicTags()` (Stats4SD leftover) and its test.

The manual test at the pause point passed on all items except facet counts: conjunctive sibling counts wrongly disabled checkboxes. Task 10 fixes that.

## Phase 2: hardening (`1f58cbc`, CI fixes `45abc1f`, `1fc87fe`)

- **Integration suite** (Task 7): `tests/Integration/Search/RealMeilisearchTest.php` is a new `Integration` testsuite in `phpunit.xml`.
  - It only runs with `MEILISEARCH_INTEGRATION=1`, uses a unique `scout.prefix` per run, and deletes its indexes afterwards.
  - It covers: index-settings sync, federated ranking and pagination, filters, disjunctive facet counts on fixtures where conjunctive and disjunctive counts differ, the shared `sort_date` federated sort, and `tag_names` searchability.
  - `.github/workflows/ci.yml` runs Unit+Feature on SQLite and, in a separate job, runs Integration against a `getmeili/meilisearch` service container.
- **Reconciliation and ops docs** (Task 8): `app/Console/Kernel.php` schedules a nightly `scout:import` for `Trove` and `Collection`, gated on `scout.driver === 'meilisearch'` (covered by `tests/Feature/Console/ScheduleTest.php`). `README.md` documents the fallback listing, the first-deploy and schema-change steps, `SCOUT_QUEUE` with a worker, and the scheduler requirement.
- **Disjunctive facet counts** (Task 10): for each dimension with an active selection, `MeilisearchLibrarySearch` recomputes that dimension's counts without its own filter clause.
  - All of those recomputes go out as one extra non-federated `multiSearch()` with `limit: 0`, and the per-index counts are summed in PHP. Nothing extra is sent when no filter is active.
  - In the blade, zero-count options are muted but never `disabled`.
- **Task 9, Scout 11 upgrade: deferred.** `kainiklas/filament-scout` v1.1.0 requires `laravel/scout ^10.5`. Not forked; nothing in this milestone needs Scout 11. Tracked in [issue #4](https://github.com/stats4sd/digital-sovereignty-library/issues/4).

## Follow-ups on `dev`

- `302bf83`: clearer "text search unavailable" notice.
- `fff3c4d`: the resource-type filter can be switched off. New `SiteSetting.show_trove_type_filter`, exposed on `SiteOptionsPage` and hydrated into `config('branding.features.show_trove_type_filter')`.
- `725ec11`: fixed the "Showing X–Y of Z" text in the results header, with tests.
- `a42563d`: aligned the fallback-notice assertion in `BrowseAllTest` with the reworded text.

## Verification

- Unit+Feature on SQLite: 359 passed, 6 skipped (at Phase 2 completion).
- Integration suite: 5 passed locally against Meilisearch 1.49.
- Manual pause-point checklist completed. See the plan.
