<?php

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Resources\CurriculumSessionResource;
use App\Models\CurriculumModule;
use App\Models\CurriculumSession;

it('forbids a viewer from editing a curriculum module but lets them list modules', function () {
    actingAsViewer();

    $module = CurriculumModule::factory()->map()->create();

    $this->get(CurriculumModuleResource::getUrl('edit', ['record' => $module]))->assertForbidden();
    $this->get(CurriculumModuleResource::getUrl('index'))->assertOk();
});

it('forbids a viewer from editing a curriculum session', function () {
    actingAsViewer();

    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();

    $this->get(CurriculumSessionResource::getUrl('edit', ['record' => $session]))->assertForbidden();
});

it('lets an editor edit a curriculum module and a curriculum session', function () {
    actingAsEditor();

    $module = CurriculumModule::factory()->map()->create();
    $session = CurriculumSession::factory()->for($module, 'module')->create();

    $this->get(CurriculumModuleResource::getUrl('edit', ['record' => $module]))->assertOk();
    $this->get(CurriculumSessionResource::getUrl('edit', ['record' => $session]))->assertOk();
});
