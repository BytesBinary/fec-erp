<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised after a user's 2FA was disabled or reset by an admin. MCP access
 * management listens to this to revoke the user's AI integrations (spec §4.5).
 */
class TwoFactorDeactivated
{
    use Dispatchable;

    public function __construct(public User $user, public string $reason) {}
}
