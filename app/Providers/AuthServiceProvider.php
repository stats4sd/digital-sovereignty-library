<?php

namespace App\Providers;

use App\Models\CurriculumModule;
use App\Models\User;
use App\Policies\CurriculumModulePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        // The curriculum preview (/preview/curriculum/...) shows draft content, so it is open
        // to anyone who may edit it: editors and admins. Viewers get a 403.
        Gate::define('preview-curriculum', fn (User $user): bool => $user->canEdit());
    }
}
