# Curriculum content

The seed content for the course, as plain YAML: modules and their learning outcomes, the Module 3 sessions and their content items, the glossary, and the toolkit's tag type, pillar tags and tool Troves. `Database\Seeders\Prep\CurriculumSeeder` and `ToolkitToolsSeeder` read these files; nothing here is authored in the admin panel, but once seeded every row is editable there and a re-run of the seeders never overwrites a row that already exists (see "Idempotency").

## Layout

| File | Contents |
|---|---|
| `modules/<key>.yaml` | One `CurriculumModule` per file: `key`, `section` (`intro` / `map` / `toolkit`), `number` (map modules only), `title`, `subtitle`, `description` (HTML), `note`, `goal`, `learning_outcomes`, `sessions`. |
| `glossary.yaml` | `terms`: list of `{ term, definition }` for `GlossaryTerm`. |
| `toolkit/tags.yaml` | The `tools` `TagType` (`slug`, `label`, `description`, `freetext: false`, `show_in_filter: true`) and `pillar_tags`, one `Tag` name per toolkit pillar module key. |
| `toolkit/tools/<pillar>.yaml` | Seeded tool Troves for that pillar, in display order: `title`, `description` (HTML), optional `published: false`. |

Every translatable field is a map keyed by locale (`en`, `ar`, `es`, `fil`, `fr`, `hi`, `id`, `it`, `pt`, `ru`, `sw`, `zh_CN`). English is always present; a missing locale falls back to English at render time. Module, glossary and toolkit fields currently carry all twelve locales; session items are English only so far.

## Shapes

- `learning_outcomes` is an ordered list of `{ statement, in_practice? }`. The seeder adds a `key` (UUID) per outcome at seed time; it is not content and is not stored here.
- `sessions` (currently only on `community-needs`) is an ordered list of `{ slug, builds_toward, title, summary, items? }`. `builds_toward` is the 1-based position of the outcome in that module's `learning_outcomes`; the seeder resolves it to the stored outcome's `key`.
- `items` is the session's ordered content, one `CurriculumSessionItem` row each: `{ key, type, config }`.
  - `key` is a fixed UUID. It is the row's identity across re-seeds and the namespace learners' browser-side notes are saved under, so it must never change once the content is live.
  - `type` is a `CurriculumItemType` value: `prose`, `callout`, `note_prompt`, `note_canvas`, `note_matrix` or `quiz`. (`trove` items are not seeded; attach resources in the admin.)
  - `config` follows that type's `ItemDefinition::configKeys()` in `app/Curriculum/Items/`; translatable leaves (`heading`, `body`, `prompt`, `fields.*.label`, `items.*.stem`, …) are locale maps, HTML leaves (`body`) are sanitised on seed. The seeder runs every `config` through the same `normalise()` the admin Builder uses, so invalid seed content fails the seed with a validation message rather than reaching the database.
  - Only trove items have an `intro`; an activity's instruction paragraph is an unheaded `prose` item placed before it.
- Tool Troves are created with `trove_type` = the `Tool` `TroveType`, `source: true`, `creation_date: today`, `uploader_id` = first user (or a `Library System` user), `published_at: now()` unless `published: false`. Each is attached to its pillar module's `troves` pivot with `order_column` = list position (1-based) and tagged with the matching pillar tag.

## Idempotency

Rows are matched on `key` (modules and items), `slug` within the module (sessions), `term->en` (glossary), `title->en` (tools), `slug` (tag type) and `type_id + name->en` (tags). An existing row is left alone, so admin edits survive `db:seed`; a row deleted in the admin is recreated. A recreated or newly added item is appended after the session's existing items (the admin Builder renumbers positions on every save, so the YAML order only holds on a fresh database). Items removed from the YAML are not deleted from the database.

## Formatting note

Multi-paragraph HTML has a newline between block-level tags (`</p>`, `</h2>`) so it reads as a YAML literal block. The difference is insignificant in HTML.
