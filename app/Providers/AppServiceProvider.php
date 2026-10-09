<?php

namespace App\Providers;

use App\Listeners\RecordRoleAndPermissionChanges;
use App\Policies\RolePolicy;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\PermissionCatalog;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(AuditLogger::class);
        $this->app->singleton(PermissionCatalog::class);
        $this->app->scoped(Authorizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Event::subscribe(RecordRoleAndPermissionChanges::class);
    }
}
