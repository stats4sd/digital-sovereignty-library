# Collapse alter/data migrations into their `create` migrations

**Date:** 2026-09-21
**Branch:** `content-updates`
**Plan:** [docs/plans/2026-09-21-collapse-migrations-for-fresh-deploy.md](../plans/2026-09-21-collapse-migrations-for-fresh-deploy.md)

There is no live database yet, so staging and production will both be brought up with `php artisan migrate:fresh --seed`. `database/migrations/` is now 25 pure `create` migrations; every column add/rename/drop has been folded into the migration that creates the table, and the data migrations that only upgraded a database shape that will never have existed are gone.

## ⚠️ Every existing environment must be `migrate:fresh`ed once

Any database that ran the old migration history cannot be incrementally migrated past this change. Laravel sees the folded `create` migrations as already run and the deleted alters no longer exist, so `php artisan migrate` reports nothing to do while the schema may be missing columns (or, on a database that never ran the later alters, it will report "table already exists"). Run `php artisan migrate:fresh --seed` (or `php artisan app:fresh`) once on every local and shared dev database after pulling this.

## Migrations

Folded and deleted:

| Deleted | Folded into |
|---|---|
| `2026_07_06_154200_add_open_registration_to_site_settings` | `2024_10_05_000001_create_site_settings_table` (`show_trove_type_filter` then `open_registration`, matching the order the two `->after('show_language_filter')` alters produced) |
| `2026_07_21_120000_add_show_trove_type_filter_to_site_settings` | same |
| `2026_07_08_190603_rename_youtube_links_to_video_links_on_troves` | `2023_11_24_132900_create_troves_table` (column is `video_links` from the start) |
| `2026_09_08_100000_add_number_and_goal_to_curriculum_modules` | `2026_08_20_100000_create_curriculum_modules_table` (`number` after `key`, `goal` after `learning_outcomes`) |
| `2026_09_18_100000_drop_source_from_glossary_terms_table` | `2026_08_20_100200_create_glossary_terms_table` (`source`/`source_url` never created) |

Deleted outright:

- `2026_07_06_154000_assign_admin_role_to_existing_users` (no-op on a fresh install; `RoleSeeder` creates the roles).
- `2026_09_08_100300_convert_curriculum_learning_outcomes_to_structured` (converted a column shape that no longer exists; `CurriculumSeeder` writes the structured list directly).

Also deleted, not in the plan: **`database/schema/mysql-schema.sql`**. This was a stale `schema:dump` from July (21 migrations recorded, `troves.youtube_links` still present). Laravel loads a schema dump on any MySQL database with no migrations table before running the remaining migrations, so with the alter migrations gone a fresh MySQL install would have come up without `video_links` or `show_trove_type_filter`. There is no schema dump now; a fresh install runs the 25 migrations.

## Tests

- Deleted `tests/Feature/Migrations/CurriculumSessionsMigrationTest.php` (all three tests `include`d the deleted conversion migration).
- `tests/Pest.php`: the global `beforeEach` now runs `RoleSeeder` instead of only flushing spatie's permission cache. The deleted `assign_admin_role` migration had been creating the three roles as a side effect, and eleven tests that assign roles by name (registration, `CreateUser`, `user:set-role`, `UserPolicy`, password setup) silently depended on it. Seeding roles per test mirrors production, which always runs `migrate --seed`. The seeder also flushes the cache.
- `LegacyYoutubeLinksConverter::convertTranslations()` and its two unit tests removed (plan step 5, option a). Its only caller was the deleted rename migration; `convertLocaleEntries()` stays for `ExampleDataSeeder`.

## Docs

- `CLAUDE.md` no longer describes `video_links` as renamed from `youtube_links` with an in-place converter.
- [2026-09-08-curriculum-sessions-module-page.md](../plans/2026-09-08-curriculum-sessions-module-page.md) and [curriculum-sessions-module-page.md](curriculum-sessions-module-page.md) carry a one-line note pointing here.

## Not done

- Plan step 4 (removing `CurriculumSeeder::backfillModule()` / `refreshLegacyCommunityNeeds()` and their tests) was deliberately skipped; the seeders are being reworked separately. The `refreshLegacyCommunityNeeds()` docblock still refers to "migration 100300".

## Verification

- `php artisan migrate:fresh --seed` and `php artisan app:fresh` both succeed on a scratch MySQL 9.7 database.
- `SHOW CREATE TABLE` for every table, diffed against a `migrate:fresh` of the previous commit (which loaded the stale schema dump then ran the alters): identical after stripping `AUTO_INCREMENT` and the dump's explicit `CHARACTER SET utf8mb4` spelling. Column order in `site_settings`, `curriculum_modules`, `troves` and `glossary_terms` matches what the alters produced.
- `php artisan test --testsuite=Unit,Feature`: 471 passed, 1 skipped, 1 failed (the pre-existing, unrelated `BrowseAllTest` database-fallback failure).
