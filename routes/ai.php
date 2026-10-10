<?php

use App\Http\Middleware\AuthenticateMcpIntegration;
use App\Http\Middleware\ThrottleMcpCalls;
use App\Mcp\Servers\ErpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP server routes (spec §4.1)
|--------------------------------------------------------------------------
|
| Streamable HTTP at /mcp (bearer integration token, no session/CSRF) and a
| stdio entrypoint for local development: `php artisan mcp:start erp` with
| ERP_MCP_TOKEN set (see README_MCP.md).
*/

Mcp::web('/mcp', ErpServer::class)->middleware([AuthenticateMcpIntegration::class, ThrottleMcpCalls::class]);

Mcp::local('erp', ErpServer::class);
