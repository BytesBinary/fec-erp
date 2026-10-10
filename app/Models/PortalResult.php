<?php

namespace App\Models;

use App\Enums\PortalChangeType;
use App\Enums\PortalExamKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course grade as published by the exam portal. Older attempts of the same
 * course are kept (`is_current = false`) so a retake or improvement shows what
 * it replaced.
 */
class PortalResult extends Model
{
    /** @use HasFactory<\Database\Factories\PortalResultFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'result_pull_id',
        'portal_exam_id',
        'exam_title',
        'exam_kind',
        'course_code',
        'course_title',
        'credits',
        'letter',
        'grade_point',
        'is_current',
        'change_type',
        'previous_letter',
        'previous_grade_point',
        'fetched_at',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function pull(): BelongsTo
    {
        return $this->belongsTo(ResultPull::class, 'result_pull_id');
    }

    /**
     * @param  Builder<PortalResult>  $query
     * @return Builder<PortalResult>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    protected function casts(): array
    {
        return [
            'exam_kind' => PortalExamKind::class,
            'change_type' => PortalChangeType::class,
            'credits' => 'float',
            'grade_point' => 'float',
            'previous_grade_point' => 'float',
            'is_current' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }
}
