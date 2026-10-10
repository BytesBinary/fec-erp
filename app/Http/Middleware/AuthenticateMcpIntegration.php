<?php

namespace App\Http\Middleware;

use App\Exceptions\Mcp\McpAuthenticationException;
use App\Services\Mcp\IntegrationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates every MCP request by its bearer token (spec §4.1): valid, not
 * expired, not revoked, user active, 2FA still on, MCP enabled for the role.
 * The request then runs as that real user — never as a superuser.
 */
class AuthenticateMcpIntegration
{
    public function __construct(protected IntegrationService $integrations) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $integration = $this->integrations->authenticate($request->bearerToken(), $request->ip());
        } catch (McpAuthenticationException $exception) {
            return response()->json([
                'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()],
            ], 401, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }

        Auth::guard('web')->setUser($integration->user);
        Auth::shouldUse('web');

        app()->instance('mcp.integration', $integration);
        $request->attributes->set('mcp_integration', $integration);

        return $next($request);
    }
}
