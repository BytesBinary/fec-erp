<?php

namespace App\Enums;

/**
 * held (waiting for the daily digest) → digested; queued → sent | failed;
 * skipped (no valid address / duplicate); blocked (rendered text looked like a secret).
 */
enum EmailDeliveryStatus: string
{
    case Held = 'held';
    case Digested = 'digested';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Blocked = 'blocked';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed, self::Blocked => 'danger',
            self::Queued => 'info',
            self::Held, self::Digested => 'gray',
            self::Skipped => 'warning',
        };
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Failed, self::Skipped, self::Blocked], true);
    }
}
