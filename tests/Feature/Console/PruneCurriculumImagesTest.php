<?php

use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Support\Curriculum\ItemImages;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
    $this->session = CurriculumSession::factory()->create();
});

/** Stores a file in the image directory, backdating its mtime by $hoursOld. */
function storedImage(string $name, int $hoursOld = 48): string
{
    $path = ItemImages::DIRECTORY.'/'.$name;
    ItemImages::disk()->put($path, 'bytes');
    touch(ItemImages::disk()->path($path), time() - $hoursOld * 3600);

    return $path;
}

it('deletes an unreferenced file older than 24 hours', function () {
    $path = storedImage('orphan.jpg');

    $this->artisan('app:prune-curriculum-images')->assertOk();

    expect(ItemImages::disk()->exists($path))->toBeFalse();
});

it('keeps a file referenced by a live item', function () {
    $path = storedImage('live.jpg');
    CurriculumSessionItem::factory()->for($this->session, 'session')->image($path)->create();

    $this->artisan('app:prune-curriculum-images')->assertOk();

    expect(ItemImages::disk()->exists($path))->toBeTrue();
});

it('keeps a file referenced only by a draft item', function () {
    $path = storedImage('draft.jpg');
    CurriculumSessionItem::factory()->for($this->session, 'session')->image($path)->draft()->create();

    $this->artisan('app:prune-curriculum-images')->assertOk();

    expect(ItemImages::disk()->exists($path))->toBeTrue();
});

it('keeps a recent unreferenced file', function () {
    $path = storedImage('recent.jpg', 1);

    $this->artisan('app:prune-curriculum-images')->assertOk();

    expect(ItemImages::disk()->exists($path))->toBeTrue();
});

it('lists orphans but keeps them on --dry-run', function () {
    $path = storedImage('orphan.jpg');

    $this->artisan('app:prune-curriculum-images', ['--dry-run' => true])
        ->expectsOutputToContain($path)
        ->assertOk();

    expect(ItemImages::disk()->exists($path))->toBeTrue();
});
