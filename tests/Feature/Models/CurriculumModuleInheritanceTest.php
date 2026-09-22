<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\IntroModule;
use App\Models\MapModule;
use App\Models\ToolkitModule;

it('hydrates rows as the child class matching their section', function () {
    $map = CurriculumModule::factory()->map()->create();
    $toolkit = CurriculumModule::factory()->toolkit()->create();
    $intro = CurriculumModule::factory()->intro()->create();

    expect(CurriculumModule::find($map->id))->toBeInstanceOf(MapModule::class)
        ->and(CurriculumModule::find($toolkit->id))->toBeInstanceOf(ToolkitModule::class)
        ->and(CurriculumModule::find($intro->id))->toBeInstanceOf(IntroModule::class)
        ->and(CurriculumModule::find($intro->id))->not->toBeInstanceOf(MapModule::class);
});

it('scopes child queries to their own section and fixes the section on create', function () {
    CurriculumModule::factory()->map()->count(2)->create();
    CurriculumModule::factory()->toolkit()->create();
    CurriculumModule::factory()->intro()->create();

    expect(MapModule::count())->toBe(2)
        ->and(ToolkitModule::count())->toBe(1)
        ->and(CurriculumModule::count())->toBe(4);

    $created = MapModule::create(['key' => 'fresh-node', 'title' => ['en' => 'Fresh node']]);

    expect($created->fresh()->section)->toBe(CurriculumModule::SECTION_MAP)
        ->and(MapModule::where('key', 'fresh-node')->exists())->toBeTrue();
});

it('builds child instances from the child factories', function () {
    expect(MapModule::factory()->create())->toBeInstanceOf(MapModule::class)
        ->and(ToolkitModule::factory()->create())->toBeInstanceOf(ToolkitModule::class)
        ->and(MapModule::factory()->create()->section)->toBe(CurriculumModule::SECTION_MAP);
});

it('resolves a session module as a MapModule and keeps sessions on the child', function () {
    $module = MapModule::factory()->withSessions(2)->create();
    $session = CurriculumSession::query()->where('curriculum_module_id', $module->id)->first();

    expect($session->module)->toBeInstanceOf(MapModule::class)
        ->and($module->sessions)->toHaveCount(2);
});

it('orders map modules by number then id, with unnumbered modules last', function () {
    $second = MapModule::factory()->create(['number' => 2]);
    $unnumbered = MapModule::factory()->create(['number' => null]);
    $first = MapModule::factory()->create(['number' => 1]);

    expect(MapModule::inMapOrder()->pluck('id')->all())->toBe([$first->id, $second->id, $unnumbered->id]);
});
