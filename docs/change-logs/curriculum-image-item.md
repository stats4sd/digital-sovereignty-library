# Curriculum `image` session item — Change Log

Implements [docs/plans/2026-10-16-curriculum-image-item.md](../plans/2026-10-16-curriculum-image-item.md).

**Date**: 2026-10-16
**Branch**: `dev`

## Summary

Editors can now add images to session content as a dedicated **Image** block (upload + translatable alt text + optional translatable caption + wide/medium/small size), rather than inline inside the prose/callout/resource-intro rich-text editors. Inline attachments stay disabled and `HtmlSanitizer` still strips `<img>`; that was a deliberate choice to avoid per-locale orphan uploads, widening the sanitiser allow-list, and uncontrolled layout. An image that belongs with a resource goes in an Image block next to the Resource block.

## Storage model

- `CurriculumSessionItem` is **not** a Media Library model. An image item stores a plain managed path (`curriculum/images/<name>.<ext>`) in `config.image` on the media disk (`config('media-library.disk_name')`: `public` locally, S3 in production).
- The admin field is a plain Filament `FileUpload` inside the session content Builder. Filament stores the temporary upload and rewrites the block state to the path string during form dehydration, so `CurriculumSessionContentSync` only ever sees strings. Deliberately not set: `->image()` (would admit SVG, a stored-XSS vector on a same-origin disk; an explicit jpeg/png/webp/gif allow-list is used instead), `->moveFiles()` (skips `setVisibility('public')` on S3), `->preventFilePathTampering()` (reads the allow-list from the `CurriculumSession` record, which has no such attribute), `->deleteUploadedFileUsing()` (removing in the UI is not a save).
- `App\Support\Curriculum\ItemImages` is the single place that knows the directory, resolves the disk (at call time, so `Storage::fake` applies), validates that a path is managed (`isManaged`: inside the directory, relative, no `..`), builds URLs, and deletes files that no item references. The `isManaged` rule in `ImageItem::rules()` is the only thing stopping a tampered browser value such as a Spatie media path from being saved and later deleted by cleanup.

## Cleanup

Two layers, both needed:

1. `CurriculumSessionContentSync::apply()` snapshots the session's image paths before writing, diffs against the paths still present after, and registers a `DB::afterCommit` callback that deletes the difference. The reference check is global and uses `withDrafts()`, so a file shared by two rows (Builder "clone" copies the path) or referenced from another session survives. A rollback drops the callback, so a failed save never deletes.
2. `app:prune-curriculum-images` (`--dry-run` supported, scheduled daily) lists the directory and deletes files no item references that are older than 24 hours. It exists because deleting a session or module removes items by FK cascade with no model events, and because a save that is halted by a validation error after dehydration leaves the stored file behind. The 24h grace avoids racing an in-progress save. It must query with `withDrafts()`: `DraftScope` is active in console, and without it every draft item's image would look orphaned.

## Seeding

`database/curriculum/README.md` documents the YAML shape. `config.image` is a bare filename under `database/curriculum/images/`; `CurriculumSeeder` rewrites it to `curriculum/images/<item key>.<ext>` before normalising (so the managed-path rule passes), creates the row, then copies the file inside the seed transaction. A missing source file throws, consistent with "bad YAML fails the seed". No image items were added to the live YAML in this change; if that happens, every test that runs `CurriculumSeeder` needs `Storage::fake` on the media disk or it will write to real storage.

## Files

- `app/Enums/CurriculumItemType.php`: `Image` case (label, icon, not an activity).
- `app/Curriculum/Items/ImageItem.php`: definition, fields, rules, pre-normalise coercion of `image` (array → first element, `''` → null), block label shows caption or alt.
- `app/Curriculum/Items/ItemRegistry.php`: registered after `CalloutItem` (registry order must match enum order).
- `app/Support/Curriculum/ItemImages.php`: storage helper.
- `app/Services/CurriculumSessionContentSync.php`: after-commit cleanup.
- `app/Models/CurriculumSessionItem.php`: `imageUrl()`.
- `app/Console/Commands/PruneCurriculumImages.php`, `app/Console/Kernel.php`.
- `resources/views/curriculum/items/image.blade.php`: `<figure>` with lazy `<img>`, escaped alt, `<figcaption data-glossary-scope>`; renders nothing when the path is missing or unmanaged. Tailwind utilities only, no `app.css` change.
- `database/seeders/Prep/CurriculumSeeder.php`, `database/curriculum/README.md`, `database/curriculum/images/.gitkeep`.
- `database/factories/CurriculumSessionItemFactory.php`: `image()` state.
- Tests: `tests/Unit/Curriculum/ItemDefinitionsTest.php`, `tests/Unit/Enums/CurriculumItemTypeEnumTest.php`, `tests/Unit/Support/ItemImagesTest.php`, `tests/Unit/Services/CurriculumSessionContentSyncTest.php`, `tests/Feature/Filament/CurriculumSessionContentTest.php`, `tests/Feature/Http/CurriculumSessionItemsTest.php`, `tests/Feature/Console/PruneCurriculumImagesTest.php`, `tests/Feature/Seeders/CurriculumSeederTest.php`.

## Production notes

- The S3 bucket needs CORS for the admin origin so FilePond can preview stored images; the existing Spatie upload fields have the same requirement.
- Each image costs three S3 metadata calls per admin form render (`exists`, `size`, `mimeType`). Fine at this scale.
- `<img>` carries no width/height, so expect minor layout shift. Storing dimensions at sync time is a possible follow-up.

## Testing gotcha

`Storage::fake` writes to a real shared directory (`storage/framework/testing/disks/<name>`) and wipes it on each call. Running two test processes concurrently can make upload-related tests fail spuriously. Run the suite once, not in parallel processes.
