<?php

namespace App\Support\Curriculum;

use App\Enums\CurriculumItemType;
use App\Models\CurriculumSessionItem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Storage helpers for image items. Files live on the media disk under DIRECTORY and are
 * referenced from `config.image` as a plain relative path. The disk is resolved on every
 * call so Storage::fake() and runtime config changes apply.
 */
final class ItemImages
{
    public const DIRECTORY = 'curriculum/images';

    /**
     * Stored file extensions. Derived from file content, never from the client-supplied name:
     * on the local `public` disk a file is served from the app origin with a type taken from
     * its extension, so an `.html` or `.svg` name is a stored-XSS vector.
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function diskName(): string
    {
        return (string) config('media-library.disk_name');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * Whether $path is a relative path inside DIRECTORY. This is the only guard stopping a
     * tampered browser value (e.g. a Spatie media path) from being saved and later deleted.
     */
    public static function isManaged(?string $path): bool
    {
        if ($path === null || $path === '' || str_contains($path, '\\')) {
            return false;
        }

        if (! str_starts_with($path, self::DIRECTORY.'/') || str_starts_with($path, '/')) {
            return false;
        }

        return ! in_array('..', explode('/', $path), true);
    }

    public static function url(?string $path): ?string
    {
        return self::isManaged($path) ? self::disk()->url($path) : null;
    }

    public static function isReferenced(string $path): bool
    {
        return CurriculumSessionItem::withDrafts()
            ->where('type', CurriculumItemType::Image->value)
            ->where('config->image', $path)
            ->exists();
    }

    /**
     * @param  iterable<string>  $paths
     */
    public static function deleteIfOrphaned(iterable $paths): void
    {
        foreach (collect($paths)->unique() as $path) {
            if (is_string($path) && self::isManaged($path) && ! self::isReferenced($path)) {
                self::disk()->delete($path);
            }
        }
    }
}
