<?php

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Models\MapModule;
use Filament\Facades\Filament;

beforeEach(function () {
    usePublicContext();

    $this->module = CurriculumModule::factory()->map()->create(['key' => 'scoped']);
    $this->live = CurriculumSession::factory()->for($this->module, 'module')->create(['order_column' => 1]);
    $this->draftSession = CurriculumSession::factory()->for($this->module, 'module')->draft()->create(['order_column' => 2]);
    CurriculumSessionItem::factory()->for($this->live, 'session')->prose()->create(['position' => 0]);
    CurriculumSessionItem::factory()->for($this->live, 'session')->prose()->draft()->create(['position' => 1]);
    $this->draftModule = MapModule::factory()->draft()->create(['key' => 'scoped-draft']);
});

it('hides draft rows from direct queries', function () {
    expect(CurriculumModule::where('key', 'scoped-draft')->exists())->toBeFalse()
        ->and(CurriculumSession::whereKey($this->draftSession->id)->exists())->toBeFalse()
        ->and(CurriculumSessionItem::count())->toBe(1);
});

it('hides drafts from relations and relation counts', function () {
    $module = CurriculumModule::where('key', 'scoped')->first();

    expect($module->sessions)->toHaveCount(1)
        ->and($module->sessions->first()->is($this->live))->toBeTrue()
        ->and($this->live->fresh()->items)->toHaveCount(1)
        ->and(CurriculumModule::where('key', 'scoped')->withCount('sessions')->first()->sessions_count)->toBe(1);
});

it('lets withDrafts opt out', function () {
    expect(CurriculumModule::withDrafts()->where('key', 'scoped-draft')->exists())->toBeTrue()
        ->and(CurriculumSession::withDrafts()->count())->toBe(2)
        ->and(CurriculumSessionItem::withDrafts()->count())->toBe(2);
});

it('shows drafts when an admin panel is current', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(CurriculumModule::where('key', 'scoped-draft')->exists())->toBeTrue()
        ->and(CurriculumSession::count())->toBe(2)
        ->and(CurriculumSessionItem::count())->toBe(2);
});

it('excludes draft items from the trove pivot relations on both sides', function () {
    $session = CurriculumSession::factory()->create();
    $live = publishedTrove(['title' => ['en' => 'Live resource']]);
    $viaDraft = publishedTrove(['title' => ['en' => 'Only via a draft item']]);
    CurriculumSessionItem::factory()->for($session, 'session')->trove($live)->create(['position' => 0]);
    CurriculumSessionItem::factory()->for($session, 'session')->trove($viaDraft)->draft()->create(['position' => 1]);

    expect($session->troves()->pluck('troves.id')->all())->toBe([$live->id])
        ->and($viaDraft->curriculumSessions()->count())->toBe(0)
        ->and($live->curriculumSessions()->count())->toBe(1);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect($session->troves()->pluck('troves.id')->all())->toBe([$live->id, $viaDraft->id])
        ->and($viaDraft->curriculumSessions()->count())->toBe(1);
});
