<?php

namespace App\Enums;

enum CourseType: string
{
    case Theory = 'theory';
    case Lab = 'lab';

    public function label(): string
    {
        return match ($this) {
            CourseType::Theory => 'Theory',
            CourseType::Lab => 'Lab / Sessional',
        };
    }

    public function color(): string
    {
        return match ($this) {
            CourseType::Theory => 'info',
            CourseType::Lab => 'warning',
        };
    }
}
