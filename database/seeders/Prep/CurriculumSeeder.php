<?php

namespace Database\Seeders\Prep;

use App\Curriculum\Items\ItemRegistry;
use App\Enums\CurriculumItemType;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\GlossaryTerm;
use App\Models\Trove;
use App\Support\Curriculum\ItemImages;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->moduleDefinitions() as $definition) {
                $this->seedModule($definition);
            }

            foreach ($this->load('glossary.yaml')['terms'] as $term) {
                GlossaryTerm::firstOrCreate(
                    ['term->en' => $term['term']['en']],
                    ['term' => $term['term'], 'definition' => $term['definition']],
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $relativePath): array
    {
        return Yaml::parseFile(database_path('curriculum/'.$relativePath));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moduleDefinitions(): array
    {
        $files = glob(database_path('curriculum/modules/*.yaml')) ?: [];
        sort($files);

        return array_map(fn (string $file): array => Yaml::parseFile($file), $files);
    }

    private function seedModule(array $definition): void
    {
        $sessions = $definition['sessions'] ?? [];

        if (array_key_exists('learning_outcomes', $definition)) {
            $definition['learning_outcomes'] = collect($definition['learning_outcomes'])
                ->map(fn (array $outcome): array => [
                    'key' => (string) Str::uuid(),
                    'statement' => $outcome['statement'],
                    'in_practice' => $outcome['in_practice'] ?? null,
                ])
                ->all();
        }

        // `draft` applies on create only (firstOrCreate), so a re-seed never flips an admin's
        // choice. The value is always passed explicitly: MapModule defaults new rows to draft,
        // and seeded content should be live unless the YAML says otherwise.
        $draft = (bool) ($definition['draft'] ?? false);

        if ($draft && ($definition['section'] ?? null) !== CurriculumModule::SECTION_MAP) {
            throw new InvalidArgumentException(
                "Module [{$definition['key']}]: only learning-map modules can be drafts; intro and toolkit modules are always live.",
            );
        }

        $attributes = collect($definition)->except(['key', 'sessions', 'draft'])->all();

        // withDrafts(): outside the admin panel DraftScope would hide an existing draft row,
        // and firstOrCreate would then try to insert a duplicate key.
        $module = CurriculumModule::withDrafts()->firstOrCreate(
            ['key' => $definition['key']],
            [...$attributes, 'is_draft' => $draft],
        );

        $this->backfillModule($module, $attributes);

        foreach ($sessions as $index => $session) {
            $sessionModel = $this->seedSession($module, $session, $index);
            $items = $session['items'] ?? [];

            $keys = array_column($items, 'key');

            if (count($keys) !== count($items) || count(array_unique($keys)) !== count($keys)) {
                throw new InvalidArgumentException(
                    "Module [{$module->key}] session [{$sessionModel->slug}]: every item needs a unique key.",
                );
            }

            // Trove items point at library troves that may not exist yet (a fresh install, or a
            // database that has not had the module's troves imported). Seeding the rest of the
            // session around a missing trove would freeze the wrong order into `position`, so
            // the session's items are seeded all-or-nothing: the session row is created, its
            // items wait for the next run. Items that already exist are still never touched.
            $missing = $this->missingTroveReferences($items);

            if ($missing !== []) {
                $this->command?->warn(sprintf(
                    'Module [%s] session [%s]: items skipped — %d trove(s) not found: %s. Import them (php artisan troves:import …) and re-run this seeder.',
                    $module->key,
                    $sessionModel->slug,
                    count($missing),
                    implode(', ', $missing),
                ));

                continue;
            }

            foreach ($items as $item) {
                $this->seedSessionItem($module, $sessionModel, $item);
            }
        }
    }

    /**
     * The `trove` references in a session's items that do not resolve to a canonical trove.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private function missingTroveReferences(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === CurriculumItemType::Trove->value)
            ->map(fn (array $item): string => is_string($item['trove'] ?? null) ? trim($item['trove']) : '')
            ->unique()
            ->reject(fn (string $reference): bool => $reference !== '' && $this->findTrove($reference) !== null)
            ->map(fn (string $reference): string => $reference === '' ? '(no `trove` given)' : $reference)
            ->values()
            ->all();
    }

    /**
     * Fills number/goal/learning_outcomes on rows that already existed before those fields
     * were introduced, without touching a value an admin has since edited — including a
     * deliberate clear to an empty array/string, which is not the same as never having been set.
     */
    private function backfillModule(CurriculumModule $module, array $attributes): void
    {
        $dirty = false;

        foreach (['number', 'goal', 'learning_outcomes'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if (! is_null($module->$field)) {
                continue;
            }

            $module->$field = $attributes[$field];
            $dirty = true;
        }

        if ($dirty) {
            $module->save();
        }
    }

    /**
     * `builds_toward` is the 1-based position of one of the module's stored learning outcomes.
     * It may be omitted for a session that supports the module as a whole rather than one
     * outcome (e.g. a "Further reading" session); the column is nullable and the public views
     * already skip the "Builds toward LO n" line when it is blank.
     */
    private function seedSession(CurriculumModule $module, array $session, int $index): CurriculumSession
    {
        $buildsToward = null;

        if (array_key_exists('builds_toward', $session) && $session['builds_toward'] !== null) {
            $outcomes = $module->learning_outcomes ?? [];
            $buildsToward = $outcomes[((int) $session['builds_toward']) - 1]['key'] ?? null;

            if ($buildsToward === null) {
                throw new InvalidArgumentException(
                    "Module [{$module->key}] session [{$session['slug']}]: builds_toward must be the 1-based position of one of the module's stored learning outcomes (or be omitted).",
                );
            }
        }

        return $module->sessions()->withDrafts()->firstOrCreate(
            ['slug' => $session['slug']],
            [
                'title' => $session['title'],
                'summary' => $session['summary'] ?? null,
                'builds_toward' => $buildsToward,
                'order_column' => $index + 1,
                'is_draft' => (bool) ($session['draft'] ?? false),
            ],
        );
    }

    /**
     * New items go after whatever the session already holds: the admin Builder renumbers
     * rows 0..n-1 on every save, so the YAML index would collide with existing positions.
     *
     * A `trove` item carries no `config`; instead it names a library trove in `trove` (the
     * trove's slug, or its exact English title) and an optional translatable HTML `intro`,
     * mirroring the columns CurriculumSessionContentSync writes for the admin Builder's
     * Resource block. The trove must already exist (import it first, e.g. with
     * `troves:import`); a missing trove fails the seed rather than silently skipping the item.
     */
    private function seedSessionItem(CurriculumModule $module, CurriculumSession $session, array $item): void
    {
        $label = "Module [{$module->key}] session [{$session->slug}] item [{$item['key']}]";

        $type = CurriculumItemType::tryFrom((string) ($item['type'] ?? ''))
            ?? throw new InvalidArgumentException("{$label}: unknown item type [".($item['type'] ?? '').'].');

        if ($session->items()->withDrafts()->where('key', $item['key'])->exists()) {
            return;
        }

        $position = ($session->items()->withDrafts()->max('position') ?? -1) + 1;
        $draft = (bool) ($item['draft'] ?? false);

        if ($type === CurriculumItemType::Trove) {
            $trove = $this->resolveTrove($label, $item['trove'] ?? null);

            $sessionItem = $session->items()->make([
                'key' => $item['key'],
                'type' => $type,
                'position' => $position,
                'config' => null,
                'trove_id' => $trove->id,
                'is_draft' => $draft,
            ]);
            $sessionItem->setTranslations('intro', $this->cleanIntro($item['intro'] ?? null));
            $sessionItem->save();

            if ($trove->published_at === null) {
                $this->command?->warn("{$label}: trove [{$trove->slug}] is not published, so this item is hidden from learners until it is.");
            }

            return;
        }

        $imageSource = null;
        $imageName = null;

        if ($type === CurriculumItemType::Image) {
            [$item, $imageSource, $imageName] = $this->prepareImage($label, $item);
        }

        $config = $this->normaliseItemConfig($label, $item, $type);

        $session->items()->create([
            'key' => $item['key'],
            'type' => $type,
            'position' => $position,
            'config' => $config,
            'trove_id' => null,
            'is_draft' => $draft,
        ]);

        if ($imageSource !== null) {
            // A re-run after a failed seed overwrites the same filename rather than duplicating.
            // Disks are configured with throw => false, so a failed write returns false silently.
            if (ItemImages::disk()->putFileAs(ItemImages::DIRECTORY, new File($imageSource), $imageName) === false) {
                throw new RuntimeException("{$label}: could not copy image [{$imageName}] to the media disk.");
            }
        }
    }

    /**
     * Resolves an image item's source file (relative to database/curriculum/images/) and points
     * `config.image` at the managed target path so the managed-path rule passes.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string} item, source path, target filename
     */
    private function prepareImage(string $label, array $item): array
    {
        $filename = $item['config']['image'] ?? null;

        if (! is_string($filename) || $filename === '' || basename($filename) !== $filename) {
            throw new RuntimeException("{$label}: image items need `config.image` set to a filename inside database/curriculum/images/.");
        }

        $source = database_path('curriculum/images/'.$filename);

        if (! is_file($source)) {
            throw new RuntimeException("{$label}: image file [{$filename}] not found in database/curriculum/images/.");
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, ItemImages::EXTENSIONS, true)) {
            throw new RuntimeException("{$label}: image file [{$filename}] must be one of: ".implode(', ', ItemImages::EXTENSIONS).'.');
        }

        // The key becomes the stored filename, so it must be a bare uuid (no separators).
        if (! is_string($item['key'] ?? null) || ! Str::isUuid($item['key'])) {
            throw new RuntimeException("{$label}: image items need a uuid `key`.");
        }

        $name = $item['key'].'.'.$extension;
        $item['config']['image'] = ItemImages::DIRECTORY.'/'.$name;

        return [$item, $source, $name];
    }

    private function resolveTrove(string $label, mixed $reference): Trove
    {
        if (! is_string($reference) || trim($reference) === '') {
            throw new InvalidArgumentException("{$label}: a trove item needs a `trove` (slug or English title).");
        }

        return $this->findTrove(trim($reference)) ?? throw new InvalidArgumentException(
            "{$label}: no trove with slug or English title [{$reference}]. Import the troves first (php artisan troves:import …).",
        );
    }

    /**
     * A canonical trove (never a shadow draft) matched on slug first, then on its English
     * title, regardless of publication state so the seed does not depend on `--publish`.
     */
    private function findTrove(string $reference): ?Trove
    {
        return Trove::withDrafts()
            ->whereNull('published_id')
            ->where(fn ($query) => $query
                ->where('slug', $reference)
                ->orWhere('title->en', $reference))
            ->orderByRaw('slug = ? desc', [$reference])
            ->first();
    }

    /**
     * The trove item's intro as a sanitised locale dictionary. A bare string is English, as
     * for every other seeded translatable leaf; empty rich text is dropped.
     *
     * @return array<string, string>
     */
    private function cleanIntro(mixed $value): array
    {
        if (is_string($value)) {
            $value = [config('app.fallback_locale', 'en') => $value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($html) => is_string($html) ? HtmlSanitizer::clean($html) : null)
            ->reject(fn ($html) => TranslatableComboField::isEmptyRichContent($html))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normaliseItemConfig(string $label, array $item, CurriculumItemType $type): array
    {
        try {
            return app(ItemRegistry::class)->for($type)->normalise($item['config'] ?? []);
        } catch (ValidationException $exception) {
            $prefix = "{$label}: ";

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [
                        $field => array_map(fn (string $message) => $prefix.$message, $messages),
                    ])
                    ->all(),
            );
        }
    }
}
