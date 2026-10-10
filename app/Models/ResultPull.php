<?php

namespace App\Models;

use App\Enums\ResultPullStatus;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to pull a student's official results from the exam portal.
 */
class ResultPull extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\ResultPullFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'requested_by',
        'trigger',
        'status',
        'attempts',
        'exams_checked',
        'results_found',
        'results_changed',
        'portal_exam_id',
        'portal_publication_id',
        'pending_checks',
        'message',
        'next_check_at',
        'queued_at',
        'started_at',
        'finished_at',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function portalResults(): HasMany
    {
        return $this->hasMany(PortalResult::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return $this->student?->authorizationScope() ?? ResourceScope::none();
    }

    protected function casts(): array
    {
        return [
            'status' => ResultPullStatus::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'next_check_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
