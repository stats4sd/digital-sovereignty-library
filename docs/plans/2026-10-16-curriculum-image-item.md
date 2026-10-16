# Curriculum: `image` session item type

**Status:** Completed — see [change log](../change-logs/curriculum-image-item.md).

## Context

Editors want images in session content. Dragging an image into the prose/callout/trove-intro RichEditor does nothing because `attachFiles` is disabled on every editor and `App\Support\HtmlSanitizer` strips `<img>`. Rather than enabling inline attachments (orphan uploads per locale, sanitiser widening, no layout control), add a dedicated **`image` content block**: one uploaded image + translatable alt text + optional translatable caption + size. Images sit between blocks, not mid-paragraph; the trove-intro case is covered by placing an image block next to the resource block.

Storage is a plain path string in the item's `config` JSON on the media disk (`config('media-library.disk_name')`, `public` locally, S3 in prod). `CurriculumSessionItem` does **not** become a Media Library model: the Builder-block → row sync works on `config` keyed by uuid, and a plain `FileUpload` dehydrates to a path string before `handleRecordUpdate` runs (verified in vendor: `BaseFileUpload::setUp()` → `beforeStateDehydrated(saveUploadedFiles)`, `shouldUpdateValidatedStateAfter: true`).

## Design

**Config shape** (`ImageItem`):

| key | type | notes |
|---|---|---|
| `image` | string | managed path `curriculum/images/<name>.<ext>`; required |
| `alt` | translatable plain text | required (TextInput) |
| `caption` | translatable plain text | nullable (Textarea, 2 rows); rendered with `{{ }}` so no sanitiser change |
| `size` | `wide` \| `medium` \| `small` | nullable, view defaults to `wide` |

**Field**: `FileUpload::make('image')->disk(ItemImages::DISK)->directory(ItemImages::DIRECTORY)->visibility('public')->acceptedFileTypes(['image/jpeg','image/png','image/webp','image/gif'])->maxSize(5120)->imageResizeTargetWidth('2000')->imageResizeMode('contain')->required()`. Deliberately **not** set: `->image()` (admits SVG → stored XSS on same-origin disk), `->multiple()`, `->moveFiles()` (skips `setVisibility('public')` on S3), `->preventFilePathTampering()` (reads from the `CurriculumSession` record, would reject every path on second save), `->deleteUploadedFileUsing()` (UI remove ≠ save).

**Helper** `App\Support\Curriculum\ItemImages` (static, resolves disk at call time so `Storage::fake` applies): `DIRECTORY = 'curriculum/images'`, `disk(): Filesystem`, `isManaged(?string $path): bool` (starts with directory, not absolute, no `..`), `url(?string $path): ?string`, `isReferenced(string $path): bool` (`CurriculumSessionItem::withDrafts()->where('type','image')->where('config->image',$path)->exists()` — works on SQLite and MySQL), `deleteIfOrphaned(iterable $paths)`.

**Cleanup**: two layers, both required.
1. `CurriculumSessionContentSync::apply()`: snapshot image paths from `$existing` rows before mutation; after writes, `DB::afterCommit` → for each old path no longer in the new set, `deleteIfOrphaned` (global reference check, because Builder "clone" copies a path so two rows can share a file). Covers removed blocks, replaced files, type-change on same key. `afterCommit` is dropped on rollback, so a failed save never deletes.
2. `app:prune-curriculum-images` console command (modelled on `PruneSupersededMedia`): list files under the directory, delete those not referenced by any item (**must** query with `withDrafts()` — `DraftScope` is active in console) and older than 24h (`lastModified`) to avoid racing a halted/in-progress save. Scheduled daily in `app/Console/Kernel.php`. Needed because session/module deletes cascade at the FK level with no model events, and a `Halt`ed save leaves the stored file behind.

## Steps

### A. Type + definition
- `app/Enums/CurriculumItemType.php`: add `case Image = 'image'` (after `Callout`, before `NotePrompt`, so it sits with the non-activity content types); add arms to `label()` ('Image'), `icon()` (`heroicon-o-photo`), `isActivity()` (false).
- `app/Curriculum/Items/ImageItem.php` extending `ItemDefinition`: `configKeys` as table above; `translatableLeaves = ['alt','caption']`; `htmlLeaves = []`; `rules()` as per design incl. a closure rule `ItemImages::isManaged`; `size` rule `nullable|in:wide,medium,small`; override `blockLabel()` to append the English/first caption or alt so collapsed blocks are distinguishable; `fields()` = FileUpload, `translatable('alt', 'Alt text', TextInput, required: true)` with helper text explaining alt vs caption, `translatable('caption', 'Caption', Textarea::rows(2))`, `Select::make('size')` (native(false), default `wide`).
- `ItemDefinition::normalise()`: `image` arrives as a string after dehydration, but defensively coerce array → first element and `''` → null before validation (small transform in `ImageItem::validateNormalised` or a new `pathLeaves()` hook — prefer a local override in `ImageItem` to avoid widening the base contract).
- `app/Curriculum/Items/ItemRegistry.php::default()`: insert `new ImageItem` in the same position as the enum case (test asserts registry order == enum order).
- `app/Support/Curriculum/ItemImages.php` as designed.

### B. Sync cleanup + model
- `app/Services/CurriculumSessionContentSync.php::apply()`: snapshot/diff/`DB::afterCommit` cleanup as designed. `toBlocks()` needs no change (config flattens; the path string hydrates into `FileUpload` via `FileUploadStateCast::set`).
- `app/Models/CurriculumSessionItem.php`: add `imageUrl(): ?string` convenience (`ItemImages::url(Arr::get($this->config, 'image'))`) for the view.

### C. Public view
- `resources/views/curriculum/items/image.blade.php`: `<figure data-item-type="image" data-image-size="{{ $size }}">` → `<img src loading="lazy" alt="{{ $item->text('alt') }}">`, `<figcaption data-glossary-scope>` when caption filled. Width classes: wide = full content width, medium ≈ `max-w-2xl mx-auto`, small ≈ `max-w-md mx-auto`. Render nothing if `imageUrl()` is null.
- `resources/css/app.css`: next to `.item-body` (~L336) add minimal `figure`/`img`/`figcaption` styling (rounded corners, border `--brand-line`, caption in footer-text colour, small). No `.description` changes.
- `session.blade.php` `$tagFor`: no change needed (image items have no heading so never reach the outline). Verify, don't assume.

### D. Prune command + schedule
- `app/Console/Commands/PruneCurriculumImages.php` (`app:prune-curriculum-images`, `--dry-run` like the media pruner): as designed.
- `app/Console/Kernel.php`: `->daily()`.

### E. Seeder + YAML
- `database/curriculum/README.md`: document `type: image` with `config: {image: <file>, alt: {en: ...}, caption: {en: ...}, size: wide}` where `image` is a filename under new `database/curriculum/images/` (add `.gitkeep`).
- `database/seeders/Prep/CurriculumSeeder.php::seedSessionItem()`: for `image` type, resolve source `database/curriculum/images/<file>`; throw `RuntimeException` if missing (bad YAML fails the seed, consistent with today); target path `curriculum/images/<item-key>.<ext>` (key is unique + immutable so re-run after a failed seed overwrites, not duplicates); swap source → target in config before `normaliseItemConfig()`; create row; `ItemImages::disk()->putFileAs(...)` inside the existing transaction. Existing-key early return already prevents re-copying.
- No image items added to live YAML in this change (every test that runs `CurriculumSeeder` would otherwise hit real storage; if that happens later, those tests need `Storage::fake`).

### F. Factory + tests
- `database/factories/CurriculumSessionItemFactory.php`: `image(string $path = 'curriculum/images/fixture.jpg')` state with alt + caption; no disk access.
- `tests/Unit/Enums/CurriculumItemTypeEnumTest.php`: add case.
- `tests/Unit/Curriculum/ItemDefinitionsTest.php`: `fixtureConfig` arm; required-leaf dataset gets `image` and `alt`; new tests: rejects unmanaged path (`../x`, `media/1/x.jpg`, absolute), rejects bad `size`, coerces array `image`.
- `tests/Unit/Services/CurriculumSessionContentSyncTest.php` (with `Storage::fake(config('media-library.disk_name'))`): replaced file deleted after commit; removed block's file deleted; type change on same key deletes; file shared by two rows kept; rollback on validation failure leaves files intact.
- `tests/Feature/Filament/CurriculumSessionContentTest.php`: save an image block via `fillForm(['content' => [[ 'type'=>'image', 'data'=>['image'=>[UploadedFile::fake()->image('a.jpg',800,600)], 'alt'=>['en'=>'..'], ...]]]])` → assert row `config.image` starts with `curriculum/images/` and `Storage::assertExists`; reload existing item (file `put()` first, else `hydrateFiles` drops it and save fails "required"). Needs GD; skip if `UploadedFile::fake()->image` unavailable.
- `tests/Feature/Http/CurriculumSessionItemsTest.php`: renders `<figure>` with `img` src/alt and caption; size attribute; missing path renders nothing.
- `tests/Feature/Console/PruneCurriculumImagesTest.php`: deletes orphan older than 24h; keeps referenced, keeps **draft-item** reference, keeps recent orphan; `--dry-run`.
- `tests/Feature/Seeders/CurriculumSeederTest.php`: image item from a temp YAML + fixture file copies to faked disk at `curriculum/images/<key>.jpg`; missing source throws.

### G. Docs
- `CLAUDE.md`: add `image` to the `CurriculumItemType` list and a line on `ItemImages` / prune command / `database/curriculum/images/`.
- `docs/plans/2026-10-16-curriculum-image-item.md`: this plan (Status line per `.claude/work-patterns.md`).
- `docs/change-logs/curriculum-image-item.md` on completion.

## Verification
- `php artisan test --testsuite=Unit,Feature` green.
- `vendor/bin/pint --dirty`.
- Manual (local, `MEDIA_DISK=public`, `storage:link`): admin → session → Content → add Image block → upload jpg, alt, caption → Save → file at `storage/app/public/curriculum/images/`, public session page shows figure; replace image → old file gone; delete block → file gone; preview route shows draft image; `php artisan app:prune-curriculum-images --dry-run` lists nothing; drop an orphan file with old mtime → listed and deleted.
- Prod/S3 note for the change log: bucket CORS must allow the admin origin for FilePond previews (same requirement as existing Spatie uploads); confirm replaced object is removed and the new one is publicly readable.

## Risks / flagged
- Path comes from the browser: the `isManaged` rule is the only thing stopping a tampered path (e.g. a Spatie media file) being saved and later deleted by cleanup. Keep it.
- Halted saves orphan the stored file until the nightly prune. Acceptable.
- Stale-tab overwrite: tab A replaces image X with Y and saves (X deleted after commit); tab B then saves stale state still pointing at X. The item now references a deleted file and Y is pruned a day later. Accepted: validating file existence in `rules()` would put disk access inside `normalise()` (breaking seeder ordering and unit tests), and last-write-wins already lost text edits before this change.
- Stored extension is derived from the sniffed content (`guessExtension`), never the client filename, and limited to `ItemImages::EXTENSIONS`; the seeder applies the same allow-list and requires a uuid key.
- Three S3 HEAD-ish calls per image per admin form render (`exists`/`size`/`mimeType`). Fine at this scale.
- No width/height on `<img>` → minor layout shift. Could store dimensions later via `getimagesize` at sync time; out of scope.
- Trove `intro` still cannot carry inline images; use an adjacent image block.
