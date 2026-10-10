<?php

namespace App\Providers;

use App\Events\McpIntegrationsStopRequested;
use App\Events\TwoFactorDeactivated;
use App\Listeners\RecordRoleAndPermissionChanges;
use App\Listeners\RememberLoginPreference;
use App\Models\User;
use App\Observers\UserPasswordObserver;
use App\Policies\RolePolicy;
use App\Services\Assistant\Contracts\AssistantProvider;
use App\Services\Assistant\Providers\ClaudeProvider;
use App\Services\Assistant\Providers\FakeProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Mcp\IntegrationService;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\LogMessenger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\PermissionCatalog;
use App\Support\RequestContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(AssistantProvider::class, fn () => config('assistant.provider') === 'fake' ? new FakeProvider : new ClaudeProvider);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Event::subscribe(RecordRoleAndPermissionChanges::class);
        Event::listen(Login::class, RememberLoginPreference::class);
        Event::listen(TwoFactorDeactivated::class, fn (TwoFactorDeactivated $event) => app(IntegrationService::class)->stopAllFor($event->user, null, 'Two-factor authentication was turned off or reset'));
        Event::listen(McpIntegrationsStopRequested::class, fn (McpIntegrationsStopRequested $event) => app(IntegrationService::class)->stopAllFor($event->user, $event->user, 'Stopped from the Devices page'));

        User::observe(UserPasswordObserver::class);

        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute((int) config('assistant.rate_limit_per_minute'))->by((string) ($request->user()?->getKey() ?? $request->ip())));
    }
}
