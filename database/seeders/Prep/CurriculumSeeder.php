<?php

namespace Database\Seeders\Prep;

use App\Curriculum\Items\ItemRegistry;
use App\Enums\CurriculumItemType;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\GlossaryTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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

        $attributes = collect($definition)->except(['key', 'sessions'])->all();

        $module = CurriculumModule::firstOrCreate(
            ['key' => $definition['key']],
            $attributes,
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

            foreach ($items as $item) {
                $this->seedSessionItem($module, $sessionModel, $item);
            }
        }
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

    private function seedSession(CurriculumModule $module, array $session, int $index): CurriculumSession
    {
        $outcomes = $module->learning_outcomes ?? [];
        $buildsToward = $outcomes[($session['builds_toward'] ?? 0) - 1]['key'] ?? null;

        if ($buildsToward === null) {
            throw new InvalidArgumentException(
                "Module [{$module->key}] session [{$session['slug']}]: builds_toward must be the 1-based position of one of the module's stored learning outcomes.",
            );
        }

        return $module->sessions()->firstOrCreate(
            ['slug' => $session['slug']],
            [
                'title' => $session['title'],
                'summary' => $session['summary'],
                'builds_toward' => $buildsToward,
                'order_column' => $index + 1,
            ],
        );
    }

    /**
     * New items go after whatever the session already holds: the admin Builder renumbers
     * rows 0..n-1 on every save, so the YAML index would collide with existing positions.
     */
    private function seedSessionItem(CurriculumModule $module, CurriculumSession $session, array $item): void
    {
        $label = "Module [{$module->key}] session [{$session->slug}] item [{$item['key']}]";

        $type = CurriculumItemType::tryFrom((string) ($item['type'] ?? ''))
            ?? throw new InvalidArgumentException("{$label}: unknown item type [".($item['type'] ?? '').'].');

        if ($type === CurriculumItemType::Trove) {
            throw new InvalidArgumentException("{$label}: trove-type session items are not supported by the seeder.");
        }

        if ($session->items()->where('key', $item['key'])->exists()) {
            return;
        }

        $config = $this->normaliseItemConfig($label, $item, $type);
        $position = ($session->items()->max('position') ?? -1) + 1;

        $session->items()->create([
            'key' => $item['key'],
            'type' => $type,
            'position' => $position,
            'config' => $config,
            'trove_id' => null,
        ]);
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
