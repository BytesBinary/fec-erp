<?php

namespace App\Http\Middleware;

use App\Models\McpIntegration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-token rate limit for MCP calls; answers RATE_LIMITED (HTTP 429).
 */
class ThrottleMcpCalls
{
    public function handle(Request $request, Closure $next): Response
    {
        $integration = $request->attributes->get('mcp_integration');
        $key = 'mcp:'.($integration instanceof McpIntegration ? $integration->getKey() : $request->ip());
        $max = (int) config('mcp_access.rate_limit_per_minute');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retry = RateLimiter::availableIn($key);

            return response()->json(['error' => ['code' => 'RATE_LIMITED', 'message' => "Too many requests. Retry in {$retry} seconds.", 'retry_after' => $retry]], 429, ['Retry-After' => $retry]);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
