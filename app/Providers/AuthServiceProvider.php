<?php

namespace App\Providers;

use App\Models\CurriculumModule;
use App\Policies\CurriculumModulePolicy;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Registered explicitly (not auto-discovered) so the MapModule / ToolkitModule STI
        // children resolve to it via the Gate's parent-class lookup.
        CurriculumModule::class => CurriculumModulePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
