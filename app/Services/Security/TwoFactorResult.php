<?php

namespace App\Services\Security;

enum TwoFactorResult: string
{
    case Valid = 'valid';
    case RecoveryCodeUsed = 'recovery_code_used';
    case Invalid = 'invalid';
    case Locked = 'locked';
    case NotEnabled = 'not_enabled';

    public function isSuccess(): bool
    {
        return $this === self::Valid || $this === self::RecoveryCodeUsed;
    }
}
