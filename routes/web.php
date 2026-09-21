<?php

use App\Livewire\BrowseAll;
use App\Models\Collection;
use App\Models\IntroModule;
use App\Models\MapModule;
use App\Models\ToolkitModule;
use App\Models\Trove;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::group([
    'middleware' => 'set.locale',
], function () {

    Route::get('/', static function () {
        return redirect('/home');
    });

    Route::get('/home', function () {
        return view('home_digital_sovereignty', [
            'intro' => IntroModule::with('troves.troveType')->first(),
        ]);
    })->name('home');

    Route::get('/resources/preview/{slug}', function ($slug) {
        // Show the working version — the shadow draft when one exists, else the live/working row.
        $resource = Trove::withDrafts()->workingVersions()->where('slug', $slug)->firstOrFail();

        return view('trove', ['resource' => $resource, 'hasCollections' => $resource->collections()->where('public', 1)->exists()]);
    })->middleware('auth');

    Route::get('/resources/{troveKey}', function ($troveKey) {
        $resource = Trove::findBySlugOrRedirect($troveKey);

        if (! $resource) {
            abort(404);
        }

        // If slug doesn't match, redirect to correct slug
        if ($resource->slug !== $troveKey) {
            return redirect()->route('resources.show', ['troveKey' => $resource->slug], 301);
        }

        return view('trove', ['resource' => $resource, 'hasCollections' => $resource->collections()->where('public', 1)->exists()]);
    })->name('resources.show');

    Route::livewire('/browse-all', BrowseAll::class)->name('browse-all');

    // Curriculum: onboarding + learning map (single page, Alpine step machine).
    Route::get('/curriculum', function () {
        return view('curriculum.index', [
            'intro' => IntroModule::with('troves.troveType')->first(),
            'mapModules' => MapModule::inMapOrder()->get(),
            'pillars' => ToolkitModule::withCount('troves')->get()->keyBy('key'),
        ]);
    })->name('curriculum');

    Route::get('/curriculum/{key}', function ($key) {
        $module = MapModule::where('key', $key)
            ->with('sessions')
            ->firstOrFail();

        return view('curriculum.module', compact('module'));
    })->name('curriculum.show');

    Route::get('/curriculum/{key}/{session}', function ($key, $sessionSlug) {
        $module = MapModule::where('key', $key)
            ->with('sessions')
            ->firstOrFail();

        $session = $module->sessions->firstWhere('slug', $sessionSlug);

        if (! $session) {
            abort(404);
        }

        $session->load('items.trove.troveType');

        $index = $module->sessions->search(fn ($candidate) => $candidate->is($session));
        $previous = $index > 0 ? $module->sessions->get($index - 1) : null;
        $next = $module->sessions->get($index + 1);

        return view('curriculum.session', [
            'module' => $module,
            'session' => $session,
            'previous' => $previous,
            'next' => $next,
        ]);
    })->name('curriculum.session');

    // Toolkit pillars render as a section of /curriculum (no standalone index page);
    // only the per-pillar detail pages have their own route.
    Route::get('/toolkit/{key}', function ($key) {
        $pillar = ToolkitModule::where('key', $key)
            ->with('troves.troveType')
            ->firstOrFail();

        return view('curriculum.pillar', compact('pillar'));
    })->name('toolkit.show');

    Route::get('/collections/{id}', function ($id) {
        $collection = Collection::where('id', $id)->where('public', 1)->firstOrFail();

        return view('collection', compact('collection'));
    });

    Route::get('/download-all-zip/{slug}', function ($slug) {
        $trove = Trove::findBySlugOrRedirect($slug);

        if (! $trove) {
            abort(404);
        }

        return $trove->downloadAllFilesAsZip();
    })->name('trove.download.zip');

});
