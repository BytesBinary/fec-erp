<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamDuty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'exam_name',
        'exam_type_id',
        'start_time',
        'end_time',
        'semester',
        'batch',
        'department',
        'exam_year',
        'duty_details',
    ];

    protected $casts = [
        'duty_details' => 'array',
    ];
}
