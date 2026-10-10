<?php

namespace App\Enums;

/**
 * How a newer portal result relates to the one it replaced.
 */
enum PortalChangeType: string
{
    case Improved = 'improved';
    case Retake = 'retake';
    case Declined = 'declined';
    case Same = 'same';

    public function label(): string
    {
        return __("erp.result_pull.changes.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Improved => 'success',
            self::Retake => 'info',
            self::Declined => 'danger',
            self::Same => 'gray',
        };
    }
}
