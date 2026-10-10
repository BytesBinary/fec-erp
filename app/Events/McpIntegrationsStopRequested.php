<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user asked to stop all of their AI (MCP) integrations, e.g. from the
 * Devices page. The MCP access management listens to this (spec §3A.1, §4.5).
 */
class McpIntegrationsStopRequested
{
    use Dispatchable;

    public function __construct(public User $user, public string $reason) {}
}
