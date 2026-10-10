<?php

namespace App\Models;

use App\Enums\PortalExamKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the portal said about one student in one exam: roll, outcome, GPA,
 * CGPA and the backlog subjects. The raw page is kept on the private disk.
 */
class PortalExamResult extends Model
{
    /** @use HasFactory<\Database\Factories\PortalExamResultFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'result_pull_id',
        'portal_exam_id',
        'exam_title',
        'exam_kind',
        'exam_year',
        'exam_roll',
        'class_roll',
        'published_on',
        'outcome',
        'gpa',
        'cgpa',
        'backlog_codes',
        'raw_page_path',
        'raw_page_hash',
        'fetched_at',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'exam_kind' => PortalExamKind::class,
            'published_on' => 'date',
            'gpa' => 'float',
            'cgpa' => 'float',
            'backlog_codes' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
