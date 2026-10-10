<?php

namespace App\Enums;

/**
 * draft → submitted (teacher) → approved (department head) → published
 * (super admin / configured role). Only published results are visible to
 * students and in any read channel.
 */
enum ResultStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return __("erp.result_statuses.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Published => 'success',
        };
    }
}
