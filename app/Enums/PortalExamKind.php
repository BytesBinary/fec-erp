<?php

namespace App\Enums;

enum PortalExamKind: string
{
    case Regular = 'regular';
    case Improvement = 'improvement';
    case SpecialImprovement = 'special_improvement';

    public function isRepeat(): bool
    {
        return $this !== self::Regular;
    }
}
