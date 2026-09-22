<?php

use App\Models\SiteContent;
use Database\Seeders\Prep\SiteContentSeeder;

it('seeds the live site content for every key and locale', function () {
    $this->artisan('db:seed', ['--class' => SiteContentSeeder::class])->assertSuccessful();

    $expected = (new SiteContentSeeder)->defaults();

    expect(SiteContent::query()->count())->toBe(count($expected));

    foreach ($expected as $key => $translations) {
        $row = SiteContent::query()->where('key', $key)->firstOrFail();

        expect($row->getTranslations('value'))->toBe($translations);
    }
});

it('does not overwrite admin edits on re-seed', function () {
    (new SiteContentSeeder)->run();

    SiteContent::query()->where('key', 'library_heading_line2')->firstOrFail()
        ->setTranslation('value', 'en', 'Edited in admin')
        ->save();

    (new SiteContentSeeder)->run();

    expect(SiteContent::get('library_heading_line2'))->toBe('Edited in admin')
        ->and(SiteContent::query()->count())->toBe(count((new SiteContentSeeder)->defaults()));
});

it('runs without a command instance when seeded programmatically', function () {
    (new SiteContentSeeder)->run();

    expect(SiteContent::get('library_heading_line1'))->toBe('Digital Sovereignty')
        ->and(SiteContent::get('footer_admin_login_label'))->toBe('Login');
});
