<?php

use App\Support\TranslatableText;

beforeEach(function () {
    config(['branding.locales' => ['en' => 'English', 'fr' => 'Français', 'es' => 'Español']]);
});

it('returns the current locale value when present', function () {
    app()->setLocale('fr');

    $result = TranslatableText::pick(['en' => 'Hello', 'fr' => 'Bonjour']);

    expect($result)->toBe('Bonjour');
});

it('falls back to another configured locale when the current one is missing', function () {
    app()->setLocale('fr');

    $result = TranslatableText::pick(['es' => 'Hola']);

    expect($result)->toBe('Hola');
});

it('falls back to any filled value when no configured locale matches', function () {
    app()->setLocale('fr');

    $result = TranslatableText::pick(['de' => 'Hallo']);

    expect($result)->toBe('Hallo');
});

it('respects an explicit locale argument over the current app locale', function () {
    app()->setLocale('en');

    $result = TranslatableText::pick(['en' => 'Hello', 'es' => 'Hola'], 'es');

    expect($result)->toBe('Hola');
});

it('returns null for a null or empty translations array', function () {
    expect(TranslatableText::pick(null))->toBeNull()
        ->and(TranslatableText::pick([]))->toBeNull();
});

it('returns null when every value is blank', function () {
    $result = TranslatableText::pick(['en' => '', 'fr' => null]);

    expect($result)->toBeNull();
});

it('skips a nested-array leaf and falls through to the next locale', function () {
    app()->setLocale('en');

    $result = TranslatableText::pick(['en' => ['nested' => 'oops'], 'fr' => 'Bonjour']);

    expect($result)->toBe('Bonjour');
});

it('returns null when every leaf is a nested array', function () {
    $result = TranslatableText::pick(['en' => ['nested' => 'oops'], 'fr' => ['also' => 'nested']]);

    expect($result)->toBeNull();
});
