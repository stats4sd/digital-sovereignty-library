<?php

use App\Models\CurriculumModule;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use Database\Seeders\Prep\CurriculumSeeder;
use Database\Seeders\Prep\ToolkitToolsSeeder;
use Database\Seeders\Prep\TroveTypeSeeder;

beforeEach(function () {
    $this->seed(TroveTypeSeeder::class);
    $this->seed(CurriculumSeeder::class);
});

it('merges every locale file into the seeded tools, tag type and pillar tags', function () {
    $this->seed(ToolkitToolsSeeder::class);

    $locales = collect(glob(database_path('seeders/Prep/toolkit-translations/*.php')))
        ->map(fn ($file) => basename($file, '.php'));
    expect($locales)->toHaveCount(11);

    $liteFarm = Trove::withDrafts()->where('title->en', 'LiteFarm')->first();
    $tagType = TagType::where('slug', 'tools')->first();
    $farmTag = Tag::where('type_id', $tagType->id)->where('name->en', 'Farm')->first();

    foreach ($locales as $locale) {
        expect($liteFarm->getTranslation('description', $locale, false))->toContain('<h2>')
            ->and($tagType->getTranslation('label', $locale, false))->not->toBe('')
            ->and($farmTag->getTranslation('name', $locale, false))->not->toBe('');
    }
});

it('seeds the toolkit tools and attaches them to the pillars in order', function () {
    $this->seed(ToolkitToolsSeeder::class);

    expect(Trove::withDrafts()->count())->toBe(11);

    $market = CurriculumModule::forSection(CurriculumModule::SECTION_TOOLKIT)->where('key', 'market')->first();
    expect($market->troves()->withDrafts()->pluck('title')->map(fn ($t) => $t['en'] ?? $t)->all())
        ->toBe(['Open Food Network', 'Points of Sales', 'KPL Food Coop Market Hub']);

    // Placeholder entries stay unpublished until their content exists.
    expect(Trove::withDrafts()->where('title->en', 'Points of Sales')->first()->published_at)->toBeNull()
        ->and(Trove::withDrafts()->where('title->en', 'farmOS')->first()->published_at)->toBeNull()
        ->and(Trove::where('title->en', 'LiteFarm')->first()->published_at)->not->toBeNull();

    // Descriptions follow the tool convention: intro + "How to use it" section.
    expect(Trove::where('title->en', 'ODK (Open Data Kit)')->first()->getTranslation('description', 'en'))
        ->toContain('<h2>How to use it</h2>');
});

it('seeds a filterable Tools tag type and tags each tool with its pillar', function () {
    $this->seed(ToolkitToolsSeeder::class);

    $tagType = \App\Models\TagType::firstWhere('slug', 'tools');
    expect($tagType)->not->toBeNull()
        ->and($tagType->show_in_filter)->toBeTrue()
        ->and($tagType->tags()->count())->toBe(4);

    $tagFor = fn (Trove $trove) => $trove->tags()->pluck('name')->map(fn ($n) => $n['en'] ?? $n)->all();

    expect($tagFor(Trove::where('title->en', 'ODK (Open Data Kit)')->first()))->toBe(['Knowledge'])
        ->and($tagFor(Trove::where('title->en', 'LiteFarm')->first()))->toBe(['Farm'])
        ->and($tagFor(Trove::where('title->en', 'Open Food Network')->first()))->toBe(['Market'])
        ->and($tagFor(Trove::where('title->en', 'Tech Support by Our Sci')->first()))->toBe(['Collaboration']);

    // Re-run: no duplicate tags or attachments.
    $this->seed(ToolkitToolsSeeder::class);
    expect($tagType->tags()->count())->toBe(4)
        ->and(Trove::where('title->en', 'LiteFarm')->first()->tags()->count())->toBe(1);
});

it('is idempotent and preserves admin edits on re-run', function () {
    $this->seed(ToolkitToolsSeeder::class);

    Trove::where('title->en', 'LiteFarm')->first()
        ->update(['description' => ['en' => '<p>Edited by admin</p>']]);

    $this->seed(ToolkitToolsSeeder::class);

    expect(Trove::withDrafts()->count())->toBe(11)
        ->and(Trove::where('title->en', 'LiteFarm')->first()->getTranslation('description', 'en'))
        ->toBe('<p>Edited by admin</p>');
});
