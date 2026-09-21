# Curriculum content (interim)

Plain-data extraction of everything `Database\Seeders\Prep\CurriculumSeeder` and `ToolkitToolsSeeder` currently seed, with the English source and the eleven `*-translations/<locale>.php` files merged into one `locale => value` map per translatable field. These files exist so the content can be edited independently of seeder code while the curriculum data structures change (see [docs/plans/2026-09-21-curriculum-session-items.md](../../docs/plans/2026-09-21-curriculum-session-items.md)); Phase 6 rebuilds the seeders to load from here and the old seeders plus their translation directories are then deleted.

Nothing reads these files yet. Both existing seeders remain the live source until Phase 6.

## Layout

| File | Contents |
|---|---|
| `modules/<key>.yaml` | One `CurriculumModule` per file: `key`, `section` (`intro` / `map` / `toolkit`), `number` (map modules only), `title`, `subtitle`, `description` (HTML), `note`, `goal`, `learning_outcomes`, `sessions`. |
| `glossary.yaml` | `terms`: list of `{ term, definition }` for `GlossaryTerm`. |
| `toolkit/tags.yaml` | The `tools` `TagType` (`slug`, `label`, `description`, `freetext: false`, `show_in_filter: true`) and `pillar_tags`, one `Tag` name per toolkit pillar module key. |
| `toolkit/tools/<pillar>.yaml` | Seeded tool Troves for that pillar, in display order: `title`, `description` (HTML), optional `published: false`. |

Every translatable field is a map keyed by locale (`en`, `ar`, `es`, `fil`, `fr`, `hi`, `id`, `it`, `pt`, `ru`, `sw`, `zh_CN`). English is always present; a missing locale should fall back to English at render time, as the current seeders do. All fields currently carry all twelve locales.

## Shapes the old seeders implied

- `learning_outcomes` is an ordered list of `{ statement, in_practice? }`. The stored row also carries a `key` (UUID) per outcome that the seeder generates at seed time; it is not content and is not stored here.
- `sessions` (currently only on `community-needs`) is an ordered list of `{ slug, builds_toward, title, summary }`. `builds_toward` is the 1-based position of the outcome in that module's `learning_outcomes`; the seeder resolves it to the outcome's `key`. Session `items` (Phase 6) will be added here.
- Tool Troves were created with `trove_type` = the `Tool` `TroveType`, `source: true`, `creation_date: today`, `uploader_id` = first user (or a `Library System` user), `published_at: now()` unless `published: false`. Each was attached to its pillar module's `troves` pivot with `order_column` = list position (1-based) and tagged with the matching pillar tag.
- Identity for idempotent seeding was `key` (modules), `term->en` (glossary), `title->en` (tools), `slug` (tag type) and `type_id + name->en` (tags).

## Formatting note

Multi-paragraph HTML has a newline inserted between block-level tags (`</p>`, `</h2>`) so it reads as a YAML literal block. The seeded strings had no newlines; the difference is insignificant in HTML.
