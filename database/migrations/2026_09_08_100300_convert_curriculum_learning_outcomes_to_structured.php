<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * `learning_outcomes` moves from a translatable "one outcome per line" string to a
     * non-translatable structured array: [{"key", "statement": {locale: text}, "in_practice"}].
     * For each module, decode the legacy locale-keyed text, split each locale's lines, and pair
     * up line N across locales into structured item N. Rows already converted (a JSON list) or
     * with an empty/null value are skipped, so re-running this migration is a no-op.
     */
    public function up(): void
    {
        $modules = DB::table('curriculum_modules')->get(['id', 'learning_outcomes']);

        foreach ($modules as $module) {
            $decoded = json_decode((string) $module->learning_outcomes, true);

            if (empty($decoded) || ! is_array($decoded) || array_is_list($decoded)) {
                continue;
            }

            $items = [];

            foreach ($decoded as $locale => $text) {
                $lines = collect(preg_split('/\r\n|\r|\n/', (string) $text))
                    ->map(fn (string $line) => trim($line))
                    ->filter()
                    ->values();

                foreach ($lines as $position => $line) {
                    $items[$position]['key'] ??= (string) Str::uuid();
                    $items[$position]['statement'][$locale] = $line;
                    $items[$position]['in_practice'] ??= null;
                }
            }

            ksort($items);

            DB::table('curriculum_modules')->where('id', $module->id)->update([
                'learning_outcomes' => json_encode(array_values($items)),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * One-way conversion: the original per-line text is not recoverable from the structured
     * array (line order/whitespace is not preserved losslessly), so down() is a no-op.
     */
    public function down(): void {}
};
