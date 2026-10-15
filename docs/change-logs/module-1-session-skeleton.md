# Module 1 session skeleton + troves — Change Log

**Date**: 2026-10-15
**Source**: the "Module 1 Board" planning artifact (claude.ai), state as of 2026-10-15 16:55 UTC — four draft sessions plus parked and further-reading buckets, with Dave's per-card notes.

## Summary

Module 1 (`digital-landscape`) gets its first seeded sessions, and the 24 library resources those sessions point at get an import file. The aim is a skeleton that loads into a local database in two commands and is then refined in the admin panel, exported, and moved to a live environment for review. Nothing here is final copy: every prose/callout body is a placeholder marked `[Draft placeholder …]`, trove descriptions are one or two neutral sentences, and the quiz was written from the DOC-M1 bullet breakdown of the Big Farm-a video rather than from the video itself.

To make trove-type session items seedable at all, `CurriculumSeeder` gained support for them (it used to throw), and `builds_toward` became optional on a session.

## Files

| File | What |
|---|---|
| `database/curriculum/troves/module-1.csv` | 24 new troves in `troves:import` format — one per "not yet a trove" card on the board (the "two DPI references" card is two troves). Columns: `title:en`, `description:en`, `trove_type`, `creation_date`, `link_url`, `link_title`, `video_url`, `tag:topics`, `tag:authors`. |
| `database/curriculum/modules/digital-landscape.yaml` | `sessions:` added — 5 sessions, 53 items (29 trove items, 24 prose/callout/activity items). Module fields unchanged. |
| `database/seeders/Prep/CurriculumSeeder.php` | Seeds `trove` items (`trove:` slug or English title + `intro`); skips a session's items when a referenced trove is missing; `builds_toward` optional. |
| `database/curriculum/README.md` | Documents the above. |
| `tests/Feature/Seeders/CurriculumSeederTest.php` | Three new tests (trove items deferred then seeded in order; optional `builds_toward`; slug-before-title matching, drafts ignored). One existing assertion scoped to the module (`CurriculumSession::count()` → `$module->sessions()->count()`) because Module 1 now seeds sessions too. |

## Runbook (local)

```bash
# 0. Sanity: the YAML parses and the seeder is syntactically fine
php artisan test --filter=CurriculumSeeder

# 1. Troves — validate first, then import and publish
php artisan troves:import database/curriculum/troves/module-1.csv --uploader=d.e.mills@stats4sd.org --dry-run
php artisan troves:import database/curriculum/troves/module-1.csv --uploader=d.e.mills@stats4sd.org --publish

# 2. Sessions + items (idempotent; firstOrCreate everywhere, so admin edits survive re-runs)
php artisan db:seed --class="Database\Seeders\Prep\CurriculumSeeder"

# 3. If Meilisearch is on
php artisan scout:import "App\Models\Trove"
```

Check: `http://digital-sovereignty-library.test/curriculum/digital-landscape` should list five sessions; `/admin/curriculum-modules/3/edit` → Sessions → each session's Content tab shows the items as Builder blocks.

Order matters: if step 2 runs before step 1, the seeder creates the five session rows but warns and skips every session that references a missing trove (all five). Re-running step 2 after the import fills them in, in YAML order.

## Design decisions

- **CSV + YAML, not a SQL dump.** Slugs, `published_at`, translation JSON, `external_links`/`video_links` resolution (the ETC Group film is embedded via the YouTube adapter; the playlist is the external link), tag creation and item `config` normalisation are all done by the app's own import paths, which validate and are idempotent. A dump would hard-code ids and bypass all of that, and would not survive the existing 34 troves on the demo server.
- **Trove references by slug or English title.** New troves are referenced by their exact CSV title (so the YAML does not have to predict `Str::slug()` output); the five existing PeerTube videos by their known slugs. Slug wins when both match; shadow drafts are never matched.
- **All-or-nothing items per session when a trove is missing.** Seeding the non-trove items around a gap would fix `position` in the wrong order and the trove items would land at the end on the next run. Skipping the whole session's items keeps a fresh `migrate --seed` (no troves yet) and the test suite working, and gives a correct order once the troves exist.
- **Trove types mapped onto the existing 13.** There is no Article/Report/Podcast type, so: web articles, campaign pages and reference sites → `Website`; long reports and the illustrated UNDROP → `Book`; research papers and the Springer article → `Journal Article`; the Purdue course → `Curricula / Training Course`; CGIAR principles → `Guide`; CSIPM recordings → `Webinar`. If an `Article` or `Report` type is wanted, add it in the admin first and change the column — the import aborts on unknown types.
- **Tags.** New `topics` tags are created on import (Power concentration, Data governance, Data justice, Artificial intelligence, Land & territory, Indigenous data sovereignty, Digital public infrastructure, Rights-based approaches, Farmer perspectives, Open technology, Alternatives & resistance); `authors` holds organisations and named authors. Rename or merge freely in the admin.
- **Board → sessions mapping.** Session 1 "What's the fuss all about?" (LO1), Session 2 "Who wins? Who loses? A focus on real people" (LO2), Session 3 "AI in the Here and Now" (LO2 — the board's new session; DOC-M1 has one line on AI, so its intro is the thinnest), Session 4 "Data justice is collective — resist and build" (LO3), then "Further reading" (no outcome; library-only pieces incl. the Honor the Earth campaign, which Dave moved there). The "Module framing" and "Parked / optional" buckets (Caroline's lessons-learnt video, the unlocated Naseegh & Suko audio) were left out as asked. Card notes were honoured: TNI book placed last in Session 1 as extra reading; Head in the Cloud marked secondary to the IPES article; ETC film linked to the English version with the playlist as the all-languages link; CSIPM webinars framed as reference pieces with a to-do for timestamped links.

## Known gaps / to check in the admin

- `creation_date` is blank (→ today) for 13 of the 24 troves where the publication date was not known; set it where it matters.
- Quiz questions (Session 1) paraphrase the DOC-M1 breakdown of *Big Farm-a*; check each against the video before publishing. Dave's note: "be factual, not hyped."
- Session 2 and 3 narrative text is placeholder; Session 3 needs its own storyline.
- Written-testimony excerpts in Session 4 are as summarised on the board; verify wording against `Synthesis Assignments.docx`.
- Timestamped links into the two CSIPM webinars still to be added to their trove descriptions.
- The pre-existing test `carries the French translation of digital-landscape outcomes` fails since the 2026-10-15 reframe dropped the translations; unrelated to this change but it will show up in the same run.
- Nothing was run here: the Cowork VM has no PHP, so `php artisan test --filter=CurriculumSeeder` and the `--dry-run` are the first things to do.
