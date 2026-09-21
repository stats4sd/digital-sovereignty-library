<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared by the CurriculumModule children: every query is limited to the child's section and
 * every row created through the child gets that section. Adds no instance properties, which
 * keeps the children compatible with Livewire's lazy proxies of the parent class.
 */
trait ScopedToSection
{
    abstract public static function section(): string;

    protected static function bootScopedToSection(): void
    {
        static::addGlobalScope('section', function (Builder $query): void {
            $query->where($query->qualifyColumn('section'), static::section());
        });

        static::creating(function (CurriculumModule $module): void {
            $module->section = static::section();
        });
    }
}
