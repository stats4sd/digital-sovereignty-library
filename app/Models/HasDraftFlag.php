<?php

namespace App\Models;

use App\Models\Scopes\DraftScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * `is_draft` on curriculum content: a boolean cast plus the DraftScope global scope, which
 * hides draft rows outside the admin panel and the curriculum preview. The database default
 * is false (rows that predate the column stayed live); the models that editors create through
 * the admin default new rows to true via $attributes — see MapModule, CurriculumSession and
 * CurriculumSessionItem. Intro and toolkit modules never set it, so they are always live.
 */
trait HasDraftFlag
{
    public function initializeHasDraftFlag(): void
    {
        $this->mergeCasts(['is_draft' => 'boolean']);
    }

    protected static function bootHasDraftFlag(): void
    {
        static::addGlobalScope(new DraftScope);
    }

    /** Include draft rows regardless of context (seeder, console, explicit admin reads). */
    public function scopeWithDrafts(Builder $query): Builder
    {
        return $query->withoutGlobalScope(DraftScope::class);
    }
}
