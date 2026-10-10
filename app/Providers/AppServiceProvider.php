<?php

namespace App\Providers;

use App\Listeners\RecordRoleAndPermissionChanges;
use App\Listeners\RememberLoginPreference;
use App\Models\User;
use App\Observers\UserPasswordObserver;
use App\Policies\RolePolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\LogMessenger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\PermissionCatalog;
use App\Support\RequestContext;
use Illuminate\Auth\Events\Login;
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
        $this->app->bind(ExternalMessenger::class, LogMessenger::class);
        $this->app->singleton(\PragmaRX\Google2FA\Google2FA::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Event::subscribe(RecordRoleAndPermissionChanges::class);
        Event::listen(Login::class, RememberLoginPreference::class);

        User::observe(UserPasswordObserver::class);
    }
}
