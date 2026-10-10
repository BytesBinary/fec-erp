<?php

namespace App\Models;

use App\Enums\PortalExamKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of the university portal's exam list, saved so a student pull
 * never needs to fetch the list again.
 */
class PortalExam extends Model
{
    /** @use HasFactory<\Database\Factories\PortalExamFactory> */
    use HasFactory;

    protected $fillable = [
        'portal_exam_id',
        'program_id',
        'title',
        'kind',
        'semester',
        'exam_year',
        'session_tag',
        'status',
        'first_seen_at',
        'last_seen_at',
        'confirmed_at',
        'published_on',
        'last_checked_at',
        'check_count',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PortalExamKind::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'published_on' => 'date',
            'last_checked_at' => 'datetime',
        ];
    }
}
