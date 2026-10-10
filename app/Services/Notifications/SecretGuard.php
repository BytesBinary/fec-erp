<?php

namespace App\Services\Notifications;

/**
 * Last line of defence: an email whose text looks like it carries a
 * credential is blocked instead of sent.
 */
class SecretGuard
{
    /**
     * @var list<string>
     */
    protected const PATTERNS = [
        '/erpmcp_[A-Za-z0-9]{10,}/',
        '/bearer\s+[A-Za-z0-9._\-]{16,}/i',
        '/otpauth:\/\//i',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        '/\b(password|passwd|secret|token|api[_-]?key)\s*[:=]\s*(?=[A-Za-z0-9+\/_\-]*\d)[A-Za-z0-9+\/_\-]{8,}/i',
    ];

    public function problemIn(string $text): ?string
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return 'The text looks like it contains a credential or token.';
            }
        }

        return null;
    }
}
