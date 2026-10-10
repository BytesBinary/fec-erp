<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of the grading scale: marks in [min_mark, max_mark] → letter + grade point.
 */
class GradingScale extends Model
{
    use Auditable;

    protected $fillable = [
        'min_mark',
        'max_mark',
        'letter',
        'grade_point',
        'active_from',
    ];

    protected function casts(): array
    {
        return [
            'min_mark' => 'float',
            'max_mark' => 'float',
            'grade_point' => 'float',
            'active_from' => 'date',
        ];
    }
}
