# Curriculum admin: module list ordering, tabbed module edit page, session breadcrumbs

**Date**: 2026-10-15
**Branch**: `inline-troves`
**Plan**: none (small, direct request)

Three streamlining changes to the curriculum editing pages in the admin panel.

## Changes

- **Module list** (`app/Filament/Resources/CurriculumModuleResource.php`)
  - Default order is now intro → learning-map modules by `number` → toolkit pillars (a portable `CASE section …` sort, SQLite-safe for the tests).
  - New "Map order" column shows `number` for map modules and a dash otherwise. The section badge now reads "Intro" / "Learning map" / "Toolkit" with distinct colours; labels and colours come from a single `sectionOptions()` used by both the column and the filter. `key` moved after the title and is a muted badge.
  - `getRelations()` is keyed (`troves`, `sessions`) so the relation-manager tab key is stable in the `?relation=` query string.
- **Module edit page** (`EditCurriculumModule`, `SessionsRelationManager`, `TrovesRelationManager`)
  - Uses Filament's combined-tabs mode (`hasCombinedRelationManagerTabsWithContent()`): one "Module" tab for the module's fields, then "Sessions" (map modules) or "Resources" (intro/toolkit modules), mirroring the session edit page. The form is wrapped in a `Section` card to match.
  - Sessions table: summary and "Builds toward" columns wrap (summary limited to 160 chars), the number and block-count columns no longer grow, so the row actions stay on screen without side-scrolling.
- **Session edit page** (`EditCurriculumSession`)
  - `getBreadcrumbs()` now runs Curriculum Modules → parent module (opened on its Sessions tab) → session title → Edit, instead of going through the unlisted sessions index.
  - "Back to module" also lands on the Sessions tab.

## Tests

- `CurriculumModuleResourceTest`: list ordering and formatted section/number columns; Module + Sessions/Resources tabs per section.
- `CurriculumSessionResourceTest`: breadcrumb trail and the `relation=sessions` link.

## Notes

- `CurriculumSeederTest` "carries the French translation of digital-landscape outcomes" fails on this branch before and after these changes (the YAML has no French statement for that outcome); not addressed here.
