<?php

/*
|--------------------------------------------------------------------------
| MCP access management (spec §4.5)
|--------------------------------------------------------------------------
*/

return [

    'max_integrations' => (int) env('MCP_MAX_INTEGRATIONS', 5),

    'expiry_options_days' => [30, 90, 180, 365],

    'default_expiry_days' => 90,

    'expiring_soon_days' => 7,

    'token_prefix' => 'erpmcp_',

    'rate_limit_per_minute' => (int) env('MCP_RATE_LIMIT', 120),

    'max_page_size' => 100,

    /*
     | Token used by the stdio entrypoint (`php artisan mcp:start erp`).
     */
    'stdio_token' => env('ERP_MCP_TOKEN'),

];
