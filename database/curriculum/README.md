# Curriculum content

The seed content for the course, as plain YAML: modules and their learning outcomes, the Module 3 sessions and their content items, the glossary, and the toolkit's tag type, pillar tags and tool Troves. `Database\Seeders\Prep\CurriculumSeeder` and `ToolkitToolsSeeder` read these files; nothing here is authored in the admin panel, but once seeded every row is editable there and a re-run of the seeders never overwrites a row that already exists (see "Idempotency").

## Layout

| File | Contents |
|---|---|
| `modules/<key>.yaml` | One `CurriculumModule` per file: `key`, `section` (`intro` / `map` / `toolkit`), `number` (map modules only), `title`, `subtitle`, `description` (HTML), `note`, `goal`, `learning_outcomes`, `sessions`. |
| `glossary.yaml` | `terms`: list of `{ term, definition }` for `GlossaryTerm`. |
| `toolkit/tags.yaml` | The `tools` `TagType` (`slug`, `label`, `description`, `freetext: false`, `show_in_filter: true`) and `pillar_tags`, one `Tag` name per toolkit pillar module key. |
| `toolkit/tools/<pillar>.yaml` | Seeded tool Troves for that pillar, in display order: `title`, `description` (HTML), optional `published: false`. |
| `troves/<module>.csv` | Library troves a module's sessions reference, in `troves:import` format (see `docs/import/README.md`). Not read by the seeders: import them first with `php artisan troves:import database/curriculum/troves/<module>.csv --uploader=… --publish`. |

Every translatable field is a map keyed by locale (`en`, `ar`, `es`, `fil`, `fr`, `hi`, `id`, `it`, `pt`, `ru`, `sw`, `zh_CN`). English is always present; a missing locale falls back to English at render time. Module, glossary and toolkit fields currently carry all twelve locales; session items are English only so far.

## Shapes

- `learning_outcomes` is an ordered list of `{ statement, in_practice? }`. The seeder adds a `key` (UUID) per outcome at seed time; it is not content and is not stored here.
- `sessions` (on `community-needs` and `digital-landscape`) is an ordered list of `{ slug, builds_toward?, title, summary, items? }`. `builds_toward` is the 1-based position of the outcome in that module's `learning_outcomes`; the seeder resolves it to the stored outcome's `key`. Omit it for a session that supports the whole module rather than one outcome (e.g. Module 1's "Further reading"); the public pages then show no "Builds toward" line.
- `items` is the session's ordered content, one `CurriculumSessionItem` row each: `{ key, type, config }`.
  - `key` is a fixed UUID. It is the row's identity across re-seeds and the namespace learners' browser-side notes are saved under, so it must never change once the content is live.
  - `type` is a `CurriculumItemType` value: `prose`, `callout`, `note_prompt`, `note_canvas`, `note_matrix`, `quiz`, `trove` or `image`.
  - `config` follows that type's `ItemDefinition::configKeys()` in `app/Curriculum/Items/`; translatable leaves (`heading`, `body`, `prompt`, `fields.*.label`, `items.*.stem`, …) are locale maps, HTML leaves (`body`) are sanitised on seed. The seeder runs every `config` through the same `normalise()` the admin Builder uses, so invalid seed content fails the seed with a validation message rather than reaching the database.
  - An `image` item's `config` is `{ image, alt, caption?, size? }`: `image` is a filename inside `database/curriculum/images/` (e.g. `image: diagram.png`), `alt` is a required locale map, `caption` an optional locale map and `size` is `wide`, `medium` or `small` (optional). On first seed the file is copied to the media disk under `curriculum/images/<item key>.<ext>` and `config.image` is rewritten to that path; a missing source file fails the seed. Example: `{ key: ..., type: image, config: { image: diagram.png, alt: { en: "Data flow between farm and cloud" }, caption: { en: "Where your data goes" }, size: wide } }`.
  - A `trove` item has no `config`. Instead it has `trove` — the library trove's slug, or its exact English title (slug wins if both match) — and an optional translatable HTML `intro` ("why this is here, what to do with it"), the same two things the admin's Resource block stores. Shadow drafts are never matched; publication state is ignored, so the item is seeded even if the trove is still a draft (it just renders nothing until published). Troves are not created by this seeder: if any `trove` in a session does not resolve, that session's items are skipped with a warning — all of them, so `position` never freezes in the wrong order — and seeded on the next run once the troves exist. The intended order is `troves:import` first, `CurriculumSeeder` second.
  - Only trove items have an `intro`; an activity's instruction paragraph is an unheaded `prose` item placed before it.
- Optional `draft: true` on a learning-map module, a session or an item seeds that row as a draft: hidden from learners (`DraftScope`), editable in the admin, visible on the gated `/preview/curriculum/...` pages. Defaults to `false`. Applied on create only — the seeder never changes an existing row — so re-running never flips an admin's choice. Rejected on intro and toolkit modules, which are always live.
- Tool Troves are created with `trove_type` = the `Tool` `TroveType`, `source: true`, `creation_date: today`, `uploader_id` = first user (or a `Library System` user), `published_at: now()` unless `published: false`. Each is attached to its pillar module's `troves` pivot with `order_column` = list position (1-based) and tagged with the matching pillar tag.

## Idempotency

Rows are matched on `key` (modules and items), `slug` within the module (sessions), `term->en` (glossary), `title->en` (tools), `slug` (tag type) and `type_id + name->en` (tags). An existing row is left alone, so admin edits survive `db:seed`; a row deleted in the admin is recreated. A recreated or newly added item is appended after the session's existing items (the admin Builder renumbers positions on every save, so the YAML order only holds on a fresh database). Items removed from the YAML are not deleted from the database.

## Formatting note

Multi-paragraph HTML has a newline between block-level tags (`</p>`, `</h2>`) so it reads as a YAML literal block. The difference is insignificant in HTML.
