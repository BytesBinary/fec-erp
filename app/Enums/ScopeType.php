<?php

namespace App\Enums;

enum ScopeType: string
{
    case Global = 'global';
    case Department = 'department';
    case Hall = 'hall';
    case Course = 'course';
    case Self = 'self';

    public function label(): string
    {
        return __("erp.scope_types.{$this->value}");
    }
}
