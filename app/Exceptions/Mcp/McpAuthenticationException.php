<?php

namespace App\Exceptions\Mcp;

use RuntimeException;

/**
 * Raised when an MCP request cannot be tied to a valid, allowed integration.
 * `$errorCode` is one of TOKEN_MISSING, TOKEN_INVALID, TOKEN_REVOKED,
 * TOKEN_EXPIRED, MFA_REQUIRED, MCP_DISABLED, ACCOUNT_INACTIVE.
 */
class McpAuthenticationException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
