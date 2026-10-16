<?php

use App\Support\Curriculum\ItemImages;
use Illuminate\Support\Facades\Storage;

it('treats only relative paths inside the curriculum image directory as managed', function (?string $path, bool $managed) {
    expect(ItemImages::isManaged($path))->toBe($managed);
})->with([
    'valid' => ['curriculum/images/a.jpg', true],
    'nested' => ['curriculum/images/sub/a.jpg', true],
    'null' => [null, false],
    'empty' => ['', false],
    'directory only' => ['curriculum/images', false],
    'other directory' => ['media/1/a.jpg', false],
    'absolute' => ['/curriculum/images/a.jpg', false],
    'traversal' => ['curriculum/images/../../a.jpg', false],
    'backslash' => ['curriculum/images\\a.jpg', false],
    'sibling prefix' => ['curriculum/images-other/a.jpg', false],
]);

it('builds a url from the media disk for managed paths only', function () {
    Storage::fake(config('media-library.disk_name'));

    expect(ItemImages::url('curriculum/images/a.jpg'))->toBe(Storage::disk(config('media-library.disk_name'))->url('curriculum/images/a.jpg'))
        ->and(ItemImages::url('media/1/a.jpg'))->toBeNull()
        ->and(ItemImages::url(null))->toBeNull();
});
