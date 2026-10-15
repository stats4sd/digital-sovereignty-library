<?php

namespace App\Support\Curriculum;

use App\Models\CurriculumModule;
use App\Models\CurriculumSession;
use App\Models\Trove;

/**
 * Shared with every curriculum view as `$curriculum`: whether the page is the editor-only
 * preview, and the URL builders that keep a preview inside the preview.
 */
final class CurriculumContext
{
    public function __construct(public readonly bool $preview = false) {}

    public function mapUrl(): string
    {
        return route($this->preview ? 'curriculum.preview' : 'curriculum');
    }

    public function moduleUrl(CurriculumModule $module): string
    {
        return route($this->preview ? 'curriculum.preview.show' : 'curriculum.show', ['key' => $module->key]);
    }

    public function sessionUrl(CurriculumModule $module, CurriculumSession $session): string
    {
        return route($this->preview ? 'curriculum.preview.session' : 'curriculum.session', ['key' => $module->key, 'session' => $session->slug]);
    }

    public function troveUrl(Trove $trove): string
    {
        return $this->preview
            ? route('resources.preview', $trove->slug)
            : route('resources.show', $trove->slug);
    }

    public function learnerNamespace(CurriculumModule $module, CurriculumSession $session): string
    {
        return 'dsl:v1:'.($this->preview ? 'preview:' : '').$module->key.':'.$session->slug;
    }

    /** The public twin of the current preview page; null outside preview. */
    public function publicUrlFor(?CurriculumModule $module = null, ?CurriculumSession $session = null): string
    {
        if ($module && $session && ! $module->is_draft && ! $session->is_draft) {
            return route('curriculum.session', ['key' => $module->key, 'session' => $session->slug]);
        }

        if ($module && ! $module->is_draft) {
            return route('curriculum.show', ['key' => $module->key]);
        }

        return route('curriculum');
    }
}
