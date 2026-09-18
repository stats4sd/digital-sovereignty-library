<?php

// The set.locale middleware (tio/laravel) only honours ?locale= values listed in
// config('translation.target_locales'), which is derived from branding.locales at
// config-load time - so tests must set it explicitly alongside the branding locales.
function bootPublicSiteWithLocales(array $locales): void
{
    bootPublicSite($locales);
    config([
        'translation.source_locale' => array_key_first($locales),
        'translation.target_locales' => array_keys(array_slice($locales, 1)),
    ]);
}

it('renders the public layout left-to-right for English', function () {
    bootPublicSiteWithLocales(['en' => 'English', 'ar' => 'العربية']);

    $this->get('/home?locale=en')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false);
});

it('renders the public layout right-to-left for Arabic', function () {
    bootPublicSiteWithLocales(['en' => 'English', 'ar' => 'العربية']);

    $this->get('/home?locale=ar')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false);
});

it('treats a regional variant of an RTL language as right-to-left', function () {
    bootPublicSiteWithLocales(['en' => 'English', 'ar_EG' => 'العربية (مصر)']);

    $this->get('/home?locale=ar_EG')
        ->assertOk()
        ->assertSee('<html lang="ar-EG" dir="rtl">', false);
});

it('keeps a left-to-right locale as ltr even when RTL locales are configured', function () {
    bootPublicSiteWithLocales(['en' => 'English', 'ar' => 'العربية', 'hi' => 'हिन्दी']);

    $this->get('/home?locale=hi')
        ->assertOk()
        ->assertSee('<html lang="hi" dir="ltr">', false);
});
