<?php

namespace App\Support\Curriculum;

use Filament\Facades\Filament;

/**
 * Whether the current request may see draft curriculum content. Read by DraftScope (to decide
 * whether to filter) and by CurriculumContext (to decide whether a page is a preview), so the
 * two can never disagree.
 *
 * Drafts are visible inside the Filament panel (admins edit them there) and on the gated
 * `/preview/curriculum/...` routes. Everywhere else — public routes, console, queues, tests
 * that call usePublicContext() — they are hidden. Deliberately not keyed on
 * runningInConsole(): PHPUnit is console too, and the public-visibility tests must see the
 * scope. Console code that needs drafts (the seeder) opts out with withDrafts().
 */
final class DraftVisibility
{
    public const PREVIEW_ROUTE_PATTERNS = ['curriculum.preview', 'curriculum.preview.*'];

    public static function includesDrafts(): bool
    {
        if (Filament::getCurrentPanel() !== null) {
            return true;
        }

        $request = app()->bound('request') ? app('request') : null;

        return $request?->route()?->named(...self::PREVIEW_ROUTE_PATTERNS) ?? false;
    }
}
