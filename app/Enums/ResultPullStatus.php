<?php

namespace App\Enums;

/**
 * queued → running → success | failed; `skipped` when a pull cannot even
 * start (no registration number, department not mapped to a portal program).
 */
enum ResultPullStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Pending = 'pending';

    public function label(): string
    {
        return __("erp.result_pull.statuses.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Running => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
            self::Pending => 'warning',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Skipped, self::Pending], true);
    }
}
