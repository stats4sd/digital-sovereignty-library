<?php

namespace Database\Seeders\Content;

use App\Models\CurriculumModule;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rewrites the Module 1 ("digital-landscape") record — title, description, goal and
 * learning outcomes — to the "why digital sovereignty matters" framing agreed in
 * `Course Drafts/Elearning-Modules---Learning-Outcomes.docx` (content folder, 2026-10-15).
 *
 * Why a separate seeder: `Prep\CurriculumSeeder` is deliberately firstOrCreate — it never
 * touches a module row that already exists, so changing `digital-landscape.yaml` alone would
 * only affect fresh installs. This seeder is the one-off update for databases that already
 * hold the old record (the demo server). It is not registered in `DatabaseSeeder`; run it
 * explicitly:
 *
 *     php artisan db:seed --class="Database\Seeders\Content\Module1DigitalLandscapeSeeder"
 *
 * Behaviour:
 *  - Idempotent: re-running simply writes the same content again.
 *  - Translations: the old module text was machine-translated into 11 other locales. Those
 *    translations describe the *old* framing, so they are removed; the new text is English
 *    only and other locales fall back to English until the translation workflow is re-run.
 *    (Spatie's setTranslations() merges, so the old locales are forgotten explicitly first.)
 *  - Outcome keys: each learning outcome carries a UUID `key` that sessions reference through
 *    `builds_toward`. Keys are reused by position so any existing session link stays valid;
 *    extra outcomes get fresh UUIDs. If a session would be left pointing at a key that no
 *    longer exists the seeder aborts rather than orphan it.
 *  - `number` is pinned to 1 so the map order is unchanged.
 *
 * Keep `database/curriculum/modules/digital-landscape.yaml` in step with the content below so
 * a fresh `migrate --seed` produces the same record.
 */
class Module1DigitalLandscapeSeeder extends Seeder
{
    public const MODULE_KEY = 'digital-landscape';

    public function run(): void
    {
        DB::transaction(function (): void {
            $module = CurriculumModule::query()->where('key', self::MODULE_KEY)->first()
                ?? throw new RuntimeException(sprintf(
                    'Module [%s] does not exist yet — run Database\Seeders\Prep\CurriculumSeeder first.',
                    self::MODULE_KEY,
                ));

            $outcomes = $this->buildOutcomes($module);
            $this->guardSessionLinks($module, $outcomes);

            $this->replaceTranslations($module, 'title', ['en' => self::title()]);
            $this->replaceTranslations($module, 'description', ['en' => HtmlSanitizer::clean(self::description())]);
            $this->replaceTranslations($module, 'goal', ['en' => self::goal()]);

            $module->number = 1;
            $module->learning_outcomes = $outcomes;
            $module->save();

            $this->command?->info(sprintf(
                'Module [%s] updated: title, description, goal and %d learning outcomes (English only; other locales fall back to English).',
                self::MODULE_KEY,
                count($outcomes),
            ));
        });
    }

    // ----------------------------------------------------------------------------------------
    // Content
    // ----------------------------------------------------------------------------------------

    public static function title(): string
    {
        return 'Understanding the Digital Landscape';
    }

    /**
     * HTML, as for every module description. Opening sentence is the Learning Outcomes doc
     * verbatim; the second paragraph tells the learner what the module does for them.
     */
    public static function description(): string
    {
        return <<<'HTML'
<p>This module is an introduction to the topic: why digital sovereignty is so important in our current world, and how it relates to food systems and agroecology.</p>
<p>It looks at how a handful of technology and agriculture companies have come to control the digital infrastructure that food systems increasingly depend on, what that concentration of power means for farmers' rights, knowledge and livelihoods, and why data justice has to be understood collectively rather than as a question of who owns the files.</p>
HTML;
    }

    public static function goal(): string
    {
        return 'To build a strong foundation and help learners understand how modern digital tools and systems concentrate power, and why that matters for food sovereignty.';
    }

    /**
     * Statements are the Learning Outcomes doc verbatim (2026-10-15). The `in_practice` lines
     * are new drafts in the same style as Module 3's — edit or delete them in the admin.
     *
     * @return list<array{statement: string, in_practice: string}>
     */
    public static function outcomes(): array
    {
        return [
            [
                'statement' => 'Break down how major technology and agriculture companies merge their power by controlling essential infrastructure like digital platforms.',
                'in_practice' => 'you will be able to describe the cycle of data generation, processing and resale that links Big Tech and Big Ag, and name one example of it from your own context.',
            ],
            [
                'statement' => 'Identify clear risks where data collection, artificial intelligence (AI) and dependence on external tools threaten human rights, local cultures, traditional knowledge systems and global food security.',
                'in_practice' => 'you will be able to list the main risks — lock-in and dependency, surveillance, extraction without redistribution, and the sidelining of local knowledge — and say which ones your community already experiences.',
            ],
            [
                'statement' => "Understand that 'data justice' is not just about who owns the files; it means understanding how data belongs within a community, and what it means to have ownership over that collective wisdom.",
                'in_practice' => 'you will be able to explain, in your own words, the difference between individual data ownership and data that is embedded in a community\'s relationships, territory and shared knowledge.',
            ],
        ];
    }

    // ----------------------------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------------------------

    /**
     * Shape the outcomes the way CurriculumSeeder / the admin Repeater store them, reusing the
     * existing UUID at each position so `curriculum_sessions.builds_toward` links survive.
     *
     * @return list<array{key: string, statement: array<string, string>, in_practice: array<string, string>}>
     */
    private function buildOutcomes(CurriculumModule $module): array
    {
        $existing = $module->learning_outcomes;
        $existing = is_array($existing) && array_is_list($existing) ? $existing : [];

        return collect(self::outcomes())
            ->values()
            ->map(fn (array $outcome, int $index): array => [
                'key' => (string) ($existing[$index]['key'] ?? Str::uuid()),
                'statement' => ['en' => $outcome['statement']],
                'in_practice' => ['en' => $outcome['in_practice']],
            ])
            ->all();
    }

    /**
     * @param  list<array{key: string}>  $outcomes
     */
    private function guardSessionLinks(CurriculumModule $module, array $outcomes): void
    {
        $validKeys = array_column($outcomes, 'key');

        $orphaned = $module->sessions()
            ->whereNotNull('builds_toward')
            ->whereNotIn('builds_toward', $validKeys)
            ->pluck('slug');

        if ($orphaned->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Aborting: session(s) [%s] build toward a learning outcome that this seeder would remove. Re-point them in the admin first.',
                $orphaned->implode(', '),
            ));
        }
    }

    /**
     * Replace *all* translations of one attribute. Spatie's setTranslations() only merges the
     * locales it is given, which would leave the old machine translations in place.
     *
     * @param  array<string, string|null>  $translations
     */
    private function replaceTranslations(CurriculumModule $module, string $attribute, array $translations): void
    {
        foreach (array_keys($module->getTranslations($attribute)) as $locale) {
            $module->forgetTranslation($attribute, $locale);
        }

        $module->setTranslations($attribute, $translations);
    }
}
