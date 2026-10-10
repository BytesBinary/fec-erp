<?php

namespace App\Enums;

/**
 * Clearance request states (spec §8.4):
 *
 *   SUBMITTED → PENDING(stage) → … → READY_FOR_COLLECTION → PRINTED → COLLECTED
 *   PENDING → REJECTED(stage) → (resubmit) → PENDING(same stage)
 *   any non-terminal → CANCELLED
 */
enum ClearanceStatus: string
{
    case Submitted = 'submitted';
    case Pending = 'pending';
    case Rejected = 'rejected';
    case ReadyForCollection = 'ready_for_collection';
    case Printed = 'printed';
    case Collected = 'collected';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Submitted => [self::Pending, self::ReadyForCollection, self::Cancelled],
            self::Pending => [self::Pending, self::Rejected, self::ReadyForCollection, self::Cancelled],
            self::Rejected => [self::Pending, self::Cancelled],
            self::ReadyForCollection => [self::Printed, self::Cancelled],
            self::Printed => [self::Printed, self::Collected, self::Cancelled],
            self::Collected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Collected || $this === self::Cancelled;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    public function label(): string
    {
        return __("erp.clearance.statuses.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted, self::Pending => 'warning',
            self::Rejected, self::Cancelled => 'danger',
            self::ReadyForCollection => 'info',
            self::Printed, self::Collected => 'success',
        };
    }
}
