# Collapse alter/data migrations into their `create` migrations

**Status:** Not Started

**Branch:** `content-updates` (or a short-lived branch off it)
**Context:** There is no live database. Staging and production will both be brought up with `php artisan migrate:fresh --seed`, and [2026-09-21-curriculum-session-items.md](2026-09-21-curriculum-session-items.md) §1.3 already deleted two pivot migrations on that basis. This plan applies the same reasoning to the rest of the history.

## Goal

Reduce `database/migrations/` to pure `create` migrations by folding every column add/rename/drop into the migration that creates the table, and delete the data migrations (plus the seeder code and tests) that exist only to upgrade a database shape that will never have existed. Seven migrations go, leaving 25.

## Non-goals

- Removing package tables for installed-but-unwired packages (`connected_accounts` for socialment, `telescope_entries`). Dropping the migration without dropping the package invites confusion; revisit only if the packages are removed.
- Touching `curriculum_module_trove`. It is still the pivot for toolkit pillars (`ToolkitToolsSeeder`, `pillar.blade.php`, `partials/toolkit.blade.php`).
- Renaming `troves.source`. It looks like a leftover but is a real boolean used in the Trove form and table filters.
- Converting the locale files' `learning_outcomes` strings to structured lists. All 11 files under `database/seeders/Prep/curriculum-translations/` still use the one-statement-per-line string for five modules, so `CurriculumSeeder::translateOutcomes()` keeps its string branch.

## Steps

### 1. Fold column changes into the `create` migrations, then delete the alter migrations

| Delete | Fold into | Change to make in the `create` migration |
|---|---|---|
| `2026_07_06_154200_add_open_registration_to_site_settings.php` | `2024_10_05_000001_create_site_settings_table.php` | add `$table->boolean('open_registration')->default(false);` after `show_language_filter` |
| `2026_07_21_120000_add_show_trove_type_filter_to_site_settings.php` | same | add `$table->boolean('show_trove_type_filter')->default(false);` after `show_language_filter` |
| `2026_07_08_190603_rename_youtube_links_to_video_links_on_troves.php` | `2023_11_24_132900_create_troves_table.php` | rename the column definition to `$table->json('video_links')->nullable();` |
| `2026_09_08_100000_add_number_and_goal_to_curriculum_modules.php` | `2026_08_20_100000_create_curriculum_modules_table.php` | add `$table->unsignedTinyInteger('number')->nullable();` after `key` and `$table->json('goal')->nullable();` after `learning_outcomes` |
| `2026_09_18_100000_drop_source_from_glossary_terms_table.php` | `2026_08_20_100200_create_glossary_terms_table.php` | remove the `source` and `source_url` column definitions |

Column order inside the folded `create` migrations should match the order the alters produced (the alters used `->after()`), so a fresh schema is identical to what the alters would have built.

### 2. Delete the data migrations outright

- `2026_07_06_154000_assign_admin_role_to_existing_users.php`. Its docblock already states it is a no-op on a fresh install. `Database\Seeders\Prep\RoleSeeder` creates the roles.
- `2026_09_08_100300_convert_curriculum_learning_outcomes_to_structured.php`. It converts a locale-keyed string column that never exists on a fresh install; `CurriculumSeeder` writes the structured list directly.

### 3. Remove the tests that only exercise deleted migrations

- Delete `tests/Feature/Migrations/CurriculumSessionsMigrationTest.php` (and the now-empty `tests/Feature/Migrations/` directory). All three tests `include` the learning-outcomes conversion migration and nothing else.

### 4. Remove seeder code that only repairs pre-existing rows

In `database/seeders/Prep/CurriculumSeeder.php`:

- Delete `backfillModule()` and its call in `seedModule()`. It fills `number`/`goal`/`learning_outcomes` on rows created before those columns existed. With `firstOrCreate` on a fresh database the attributes are written on creation.
- Delete `refreshLegacyCommunityNeeds()` and the `if ($definition['key'] === 'community-needs')` call. It replaces outcomes that migration 100300 produced from the pre-sessions seed, which can no longer occur.
- Keep `translateOutcomes()` unchanged (see Non-goals).

In `tests/Feature/Seeders/CurriculumSeederTest.php`, delete the two tests:

- `replaces the legacy community-needs outcomes with the new set when untouched`
- `leaves community-needs outcomes untouched when they no longer match the legacy set`

Check whether any other test in that file asserts backfill behaviour (creating a module row with null `number`/`goal` and expecting the seeder to fill it) and remove it too.

### 5. Optional: shrink `LegacyYoutubeLinksConverter`

`App\Support\VideoLink\LegacyYoutubeLinksConverter::convertTranslations()` is only called by the deleted rename migration. `convertLocaleEntries()` is still used by `Database\Seeders\Example\ExampleDataSeeder` as a convenience for building `video_links` from YouTube IDs, so the class stays. Either:

- (a) remove `convertTranslations()` and its two tests in `tests/Unit/VideoLink/LegacyYoutubeLinksConverterTest.php`, or
- (b) leave the class alone.

Recommendation: (a), since a method with no callers is a maintenance trap. Low priority.

### 6. Update docs

- Search `docs/` and `CLAUDE.md` for references to the deleted migrations and to the `youtube_links` → `video_links` "in-place converter" wording. `CLAUDE.md` currently says the column was "renamed from `youtube_links` with an in-place converter"; change to say it has always been `video_links` on this fork.
- Update [2026-09-08-curriculum-sessions-module-page.md](2026-09-08-curriculum-sessions-module-page.md) and the sessions change log if they list the deleted migrations as deliverables, with a one-line note pointing at this plan.
- Write `docs/change-logs/collapse-migrations-for-fresh-deploy.md` on completion and flip this plan's status.

## Verification

1. `php artisan migrate:fresh --seed` on a local MySQL database succeeds and `php artisan app:fresh` succeeds.
2. Compare the resulting MySQL schema against a `migrate:fresh` run from the commit before this change (`mysqldump --no-data` both, diff). Only `AUTO_INCREMENT` values and the glossary `source`/`source_url` columns should differ.
3. `php artisan test --testsuite=Unit,Feature` is green. The pre-existing `BrowseAllTest` fallback failure is known and unrelated.
4. `grep -rn "youtube_links\|assign_admin_role\|convert_curriculum_learning_outcomes\|add_open_registration\|add_show_trove_type_filter\|add_number_and_goal\|drop_source_from_glossary" app database tests docs` returns nothing except historical change-log entries.

## Risks

- **Existing local or shared dev databases break.** Any database that already ran the old migrations will fail on the next `migrate` because the folded `create` migrations are recorded as run and the alters are gone, while Laravel sees no pending migrations. That is fine. But anyone who has *not* run the later alters will get "table already exists" or missing-column errors. Every existing environment must be `migrate:fresh`ed once after this lands. State this prominently in the change log and in the PR description.
- **Schema drift from `->after()` ordering.** Folding drops the explicit column positions. Harmless functionally, but the schema-diff check in Verification step 2 catches it if a column lands somewhere surprising.
- **Upstream template merges.** This repo forked from Stats4SD's resources template. Editing the 2023/2024 template migrations makes any future upstream merge of those files conflict. `CLAUDE.md` already states the repo is no longer tracking the template, so this is accepted.
