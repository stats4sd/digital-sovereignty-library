<?php

namespace App\Http\Controllers;

use App\Models\IntroModule;
use App\Models\MapModule;
use App\Models\ToolkitModule;
use App\Support\Curriculum\CurriculumContext;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

/**
 * Public curriculum pages and their editor-only preview twins. The queries are identical;
 * DraftScope is lifted on the preview routes (see DraftVisibility), so previews include drafts.
 */
class CurriculumController extends Controller
{
    public function index(): ViewContract
    {
        return $this->renderIndex(false);
    }

    public function module(string $key): ViewContract
    {
        return $this->renderModule($key, false);
    }

    public function session(string $key, string $session): ViewContract
    {
        return $this->renderSession($key, $session, false);
    }

    public function previewIndex(): ViewContract
    {
        return $this->renderIndex(true);
    }

    public function previewModule(string $key): ViewContract
    {
        return $this->renderModule($key, true);
    }

    public function previewSession(string $key, string $session): ViewContract
    {
        return $this->renderSession($key, $session, true);
    }

    private function renderIndex(bool $preview): ViewContract
    {
        View::share('curriculum', new CurriculumContext($preview));

        return view('curriculum.index', [
            'intro' => IntroModule::with('troves.troveType')->first(),
            'mapModules' => MapModule::inMapOrder()->get(),
            'pillars' => ToolkitModule::withCount('troves')->get()->keyBy('key'),
        ]);
    }

    private function renderModule(string $key, bool $preview): ViewContract
    {
        View::share('curriculum', new CurriculumContext($preview));

        $module = MapModule::where('key', $key)
            ->with('sessions')
            ->firstOrFail();

        return view('curriculum.module', compact('module'));
    }

    private function renderSession(string $key, string $sessionSlug, bool $preview): ViewContract
    {
        View::share('curriculum', new CurriculumContext($preview));

        $module = MapModule::where('key', $key)
            ->with('sessions')
            ->firstOrFail();

        $session = $module->sessions->firstWhere('slug', $sessionSlug);

        if (! $session) {
            abort(404);
        }

        if ($preview) {
            // Working versions: unpublished troves, and the shadow draft in place of a live canonical.
            $session->load([
                'items.trove' => fn ($query) => $query->withDrafts(),
                'items.trove.troveType',
                'items.trove.draft.troveType',
            ]);

            foreach ($session->items as $item) {
                if ($item->trove?->draft) {
                    $item->setRelation('trove', $item->trove->draft);
                }
            }
        } else {
            $session->load('items.trove.troveType');
        }

        $index = $module->sessions->search(fn ($candidate) => $candidate->is($session));
        $previous = $index > 0 ? $module->sessions->get($index - 1) : null;
        $next = $module->sessions->get($index + 1);

        return view('curriculum.session', [
            'module' => $module,
            'session' => $session,
            'previous' => $previous,
            'next' => $next,
        ]);
    }
}
