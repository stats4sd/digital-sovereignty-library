<?php

namespace App\Support;

/**
 * Resolves a single display string out of a plain locale-keyed array (not a Spatie
 * HasTranslations attribute, so Spatie's own fallback config isn't available). Used for
 * per-item translated fields nested inside a non-translatable array cast, e.g. an outcome's
 * `statement`/`in_practice`.
 */
class TranslatableText
{
    /**
     * Fallback order: the given (or current app) locale, then the other locales configured in
     * `branding.locales`, then any non-empty value at all. Returns null if nothing is filled.
     * Non-string leaves (e.g. an accidentally nested array) are skipped rather than returned.
     */
    public static function pick(?array $translations, ?string $locale = null): ?string
    {
        if (empty($translations)) {
            return null;
        }

        $locale ??= app()->getLocale();

        $orderedLocales = array_unique(array_merge(
            [$locale],
            array_keys(config('branding.locales', ['en' => 'English'])),
        ));

        foreach ($orderedLocales as $candidate) {
            $value = static::scalarLeaf($translations[$candidate] ?? null);

            if (filled($value)) {
                return $value;
            }
        }

        foreach ($translations as $value) {
            $value = static::scalarLeaf($value);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private static function scalarLeaf(mixed $value): ?string
    {
        if (is_array($value)) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
