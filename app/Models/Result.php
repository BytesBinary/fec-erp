<?php

namespace App\Models;

use App\Enums\ResultStatus;
use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = [
        'enrollment_id',
        'marks',
        'letter',
        'grade_point',
        'status',
        'entered_by',
        'approved_by',
        'published_by',
        'submitted_at',
        'approved_at',
        'published_at',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * The only constraint through which results may be read by students or
     * any read channel: unpublished results never leave the staff workflow.
     *
     * @param  Builder<Result>  $query
     * @return Builder<Result>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ResultStatus::Published->value);
    }

    public function authorizationScope(): ResourceScope
    {
        return $this->enrollment?->authorizationScope() ?? ResourceScope::none();
    }

    protected function casts(): array
    {
        return [
            'marks' => 'float',
            'grade_point' => 'float',
            'status' => ResultStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
