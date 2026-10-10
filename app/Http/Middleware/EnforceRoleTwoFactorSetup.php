<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Security\TwoFactorSettings;
use App\Models\User;
use App\Services\Security\MfaPolicy;
use App\Services\Security\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users holding a role for which super admin made 2FA mandatory are sent to
 * the 2FA setup page after login until they finish setting it up.
 */
class EnforceRoleTwoFactorSetup
{
    public function __construct(protected MfaPolicy $policy, protected TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->policy->isRequiredFor($user) || $this->twoFactor->isEnabled($user)) {
            return $next($request);
        }

        if ($request->routeIs(TwoFactorSettings::getRouteName(), 'filament.erp.auth.logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'MFA_SETUP_REQUIRED', 'message' => __('erp.security.setup_required')]], 403);
        }

        if ($request->hasHeader('X-Livewire')) {
            abort(419);
        }

        return redirect()->to(TwoFactorSettings::getUrl())->with('status', __('erp.security.setup_required'));
    }
}
