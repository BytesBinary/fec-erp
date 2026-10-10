<?php

namespace App\Mcp\Methods\Concerns;

use App\Exceptions\Mcp\McpAuthenticationException;
use App\Models\McpIntegration;
use App\Services\Mcp\IntegrationService;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

/**
 * Re-checks the integration behind the current MCP request, so a token that
 * was stopped, expired or lost its 2FA stops working on a long-lived (stdio)
 * connection immediately, not only when the process restarts.
 */
trait RevalidatesIntegration
{
    /**
     * @throws JsonRpcException
     */
    protected function revalidateIntegration(JsonRpcRequest $request): void
    {
        $current = app()->bound('mcp.integration') ? app('mcp.integration') : null;

        if (! $current instanceof McpIntegration) {
            return;
        }

        try {
            $fresh = app(IntegrationService::class)->revalidate($current);
        } catch (McpAuthenticationException $exception) {
            throw new JsonRpcException("[{$exception->errorCode}] {$exception->getMessage()}", -32001, $request->id);
        }

        app()->instance('mcp.integration', $fresh);
        Auth::guard('web')->setUser($fresh->user);
    }
}
