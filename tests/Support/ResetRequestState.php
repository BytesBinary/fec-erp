<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser tests serve every request from the one long-lived test process, so
 * the authenticated user, the session attributes and request-scoped services
 * of the previous request would leak into the next browser context. This
 * middleware (prepended in {@see \Tests\BrowserTestCase}) starts every request
 * from a clean slate, like a real PHP-FPM request would.
 */
class ResetRequestState
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();
        app('session')->driver()->flush();
        app()->forgetScopedInstances();

        return $next($request);
    }
}
