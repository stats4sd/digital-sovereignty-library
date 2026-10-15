<?php

namespace App\Models\Scopes;

use App\Support\Curriculum\DraftVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides draft curriculum rows (modules, sessions, items) from every default query, including
 * relationship loads and withCount, unless the request may see drafts (DraftVisibility: the
 * Filament panel, or the gated curriculum preview routes). Mirrors PublishedScope on Trove.
 * Code that must see drafts elsewhere (the seeder) opts out with ->withDrafts().
 */
class DraftScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (DraftVisibility::includesDrafts()) {
            return;
        }

        $builder->where($model->qualifyColumn('is_draft'), false);
    }
}
