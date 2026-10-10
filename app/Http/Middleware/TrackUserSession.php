<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserSession;
use App\Services\Security\SessionTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the server-side session record on every authenticated request
 * (web, Livewire, API, assistant): revoked, expired or deactivated sessions
 * are logged out immediately (spec §3A.1). The first authenticated request of
 * a browser session creates its record.
 */
class TrackUserSession
{
    public function __construct(protected SessionTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        if ($user->is_active === false) {
            return $this->reject($request, 'account_inactive');
        }

        $session = $this->tracker->find($request);

        if ($session === null) {
            $session = $this->tracker->begin($user, $request, (bool) $request->session()->pull('erp.remember', false));
        } elseif ($session->user_id !== $user->getKey() || $session->isRevoked()) {
            return $this->reject($request, 'session_revoked');
        } elseif ($session->isExpired()) {
            $this->tracker->revoke($session, null, 'expired');

            return $this->reject($request, 'session_expired');
        } else {
            $this->tracker->touch($session);
        }

        $request->attributes->set('user_session', $session);

        return $next($request);
    }

    protected function reject(Request $request, string $code): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => strtoupper($code), 'message' => __("erp.security.{$code}")]], 401);
        }

        if ($request->hasHeader('X-Livewire')) {
            return response('', 419);
        }

        return redirect()->guest(filament()->getLoginUrl())->with('status', __("erp.security.{$code}"));
    }

    public static function currentSession(Request $request): ?UserSession
    {
        $session = $request->attributes->get('user_session');

        return $session instanceof UserSession ? $session : null;
    }
}
