<?php

namespace App\Enums;

enum AttemptType: string
{
    case Regular = 'regular';
    case Retake = 'retake';
    case Improvement = 'improvement';

    public function label(): string
    {
        return __("erp.attempt_types.{$this->value}");
    }
}
