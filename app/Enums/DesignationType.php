<?php

namespace App\Enums;

enum DesignationType: string
{
    case Teacher = 'teacher';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            DesignationType::Teacher => 'Teacher',
            DesignationType::Staff => 'Staff',
        };
    }

    public function color(): string
    {
        return match ($this) {
            DesignationType::Teacher => 'info',
            DesignationType::Staff => 'warning',
        };
    }
}
