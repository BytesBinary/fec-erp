<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user with 2FA enabled is only fully signed in once the session record
 * carries `mfa_passed_at` (set by the challenge page, or inherited from a
 * trusted-device cookie). Everything else is redirected to the challenge,
 * JSON clients get 403 MFA_CHALLENGE_REQUIRED.
 */
class EnsureTwoFactorChallengePassed
{
    public function __construct(protected TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->twoFactor->isEnabled($user)) {
            return $next($request);
        }

        $session = TrackUserSession::currentSession($request);

        if ($session === null || $session->mfa_passed_at !== null) {
            return $next($request);
        }

        if ($request->routeIs(TwoFactorChallenge::getRouteName(), 'filament.erp.auth.logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'MFA_CHALLENGE_REQUIRED', 'message' => __('erp.security.challenge_required')]], 403);
        }

        if ($request->hasHeader('X-Livewire')) {
            abort(419);
        }

        return redirect()->to(TwoFactorChallenge::getUrl());
    }
}
