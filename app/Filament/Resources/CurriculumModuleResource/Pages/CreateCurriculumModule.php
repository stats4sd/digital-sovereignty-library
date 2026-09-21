<?php

namespace App\Filament\Resources\CurriculumModuleResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use App\Models\CurriculumModule;
use App\Models\MapModule;
use App\Support\TranslatableText;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

/**
 * Creates a learning-map module. The section is fixed to `map`, the key is generated once
 * from the English title (it is the public URL segment and is immutable afterwards) and the
 * module number defaults to the next free one so the new node lands at the end of the trail.
 */
class CreateCurriculumModule extends CreateRecord
{
    use Translatable;

    protected static string $resource = CurriculumModuleResource::class;

    protected static bool $canCreateAnother = false;

    public ?string $activeLocale = null;

    public function getSubheading(): string|Htmlable
    {
        return 'Adds a new node to the learning map. Sessions and their content are added from the module page after it is created.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['section'] = CurriculumModule::SECTION_MAP;
        $data['key'] = static::uniqueKeyFor($data['title'] ?? []);

        if (blank($data['number'] ?? null)) {
            $data['number'] = min(((int) CurriculumModule::query()->max('number')) + 1, 255);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CurriculumModuleResource::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * Slug of the English title (falling back to any filled locale), suffixed -2, -3, … until
     * unused across every section, since keys share one table and one URL namespace.
     */
    public static function uniqueKeyFor(array $title): string
    {
        $base = Str::slug(TranslatableText::pick($title, 'en') ?? '');

        if ($base === '') {
            $base = 'module';
        }

        $candidate = $base;
        $suffix = 2;

        while (CurriculumModule::query()->where('key', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * The key is chosen by a check-then-insert, so two editors creating the same title at once
     * can collide on the unique index; the loser picks a fresh suffix and retries once.
     */
    protected function handleRecordCreation(array $data): MapModule
    {
        try {
            return MapModule::create($data);
        } catch (UniqueConstraintViolationException) {
            $data['key'] = static::uniqueKeyFor($data['title'] ?? []);

            return MapModule::create($data);
        }
    }
}
