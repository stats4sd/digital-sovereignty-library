<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;

beforeEach(function () {
    config(['branding.locales' => ['en' => 'English']]);
    app()->setLocale('en');
});

it('generates a slug from the title when left blank', function () {
    $module = CurriculumModule::factory()->map()->create();

    $session = CurriculumSession::factory()->for($module, 'module')->create([
        'slug' => null,
        'title' => ['en' => 'Understanding Your Operational Context'],
    ]);

    expect($session->slug)->toBe('understanding-your-operational-context');
});

it('de-duplicates the generated slug within the same module', function () {
    $module = CurriculumModule::factory()->map()->create();

    CurriculumSession::factory()->for($module, 'module')->create([
        'slug' => null,
        'title' => ['en' => 'Defining What You Actually Need'],
    ]);

    $second = CurriculumSession::factory()->for($module, 'module')->create([
        'slug' => null,
        'title' => ['en' => 'Defining What You Actually Need'],
    ]);

    expect($second->slug)->toBe('defining-what-you-actually-need-2');
});

it('does not regenerate the slug when one is already set', function () {
    $module = CurriculumModule::factory()->map()->create();

    $session = CurriculumSession::factory()->for($module, 'module')->create([
        'slug' => 'custom-slug',
        'title' => ['en' => 'Some Title'],
    ]);

    $session->update(['title' => ['en' => 'A Renamed Title']]);

    expect($session->fresh()->slug)->toBe('custom-slug');
});

it('numbers sessions by sibling position, not the raw order_column', function () {
    $module = CurriculumModule::factory()->map()->create();

    $first = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 5]);
    $second = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 7]);

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2);
});

it('falls back to sibling position for its number when order_column is null', function () {
    $module = CurriculumModule::factory()->map()->create();

    $first = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => null]);
    $second = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => null]);

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2);
});

it('resolves buildsTowardOutcome to the matching outcome and its position', function () {
    $module = CurriculumModule::factory()->map()->create([
        'learning_outcomes' => [
            ['key' => 'lo-1', 'statement' => ['en' => 'First outcome'], 'in_practice' => null],
            ['key' => 'lo-2', 'statement' => ['en' => 'Second outcome'], 'in_practice' => ['en' => 'Do something']],
        ],
    ]);

    $session = CurriculumSession::factory()->for($module, 'module')->create(['builds_toward' => 'lo-2']);

    expect($session->builds_toward_outcome)->toBe([
        'position' => 2,
        'key' => 'lo-2',
        'statement' => 'Second outcome',
        'in_practice' => 'Do something',
    ]);
});

it('returns null from buildsTowardOutcome for an unknown key', function () {
    $module = CurriculumModule::factory()->map()->create([
        'learning_outcomes' => [
            ['key' => 'lo-1', 'statement' => ['en' => 'First outcome'], 'in_practice' => null],
        ],
    ]);

    $session = CurriculumSession::factory()->for($module, 'module')->create(['builds_toward' => 'unknown-key']);

    expect($session->builds_toward_outcome)->toBeNull();
});

it('returns null from buildsTowardOutcome when unset', function () {
    $module = CurriculumModule::factory()->map()->create();

    $session = CurriculumSession::factory()->for($module, 'module')->create(['builds_toward' => null]);

    expect($session->builds_toward_outcome)->toBeNull();
});

it('defaults new sessions to draft', function () {
    $module = CurriculumModule::factory()->map()->create();

    $session = CurriculumSession::create(['curriculum_module_id' => $module->id, 'slug' => 'fresh', 'title' => ['en' => 'Fresh']]);

    expect($session->fresh()->is_draft)->toBeTrue();
});

it('gives draft sessions no number and skips them when numbering live ones', function () {
    $module = CurriculumModule::factory()->map()->create();

    $a = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 1]);
    $b = CurriculumSession::factory()->for($module, 'module')->draft()->create(['order_column' => 2]);
    $c = CurriculumSession::factory()->for($module, 'module')->create(['order_column' => 3]);

    expect($a->number)->toBe(1)
        ->and($c->number)->toBe(2)
        ->and($b->number)->toBeNull();
});
