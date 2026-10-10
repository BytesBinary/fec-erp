<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A notice for everyone, a department or a hall. Department/hall notices are
 * managed by the head/provost of that department/hall.
 */
class Notice extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\NoticeFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const AUDIENCES = ['all', 'students', 'staff'];

    protected $fillable = [
        'title',
        'body',
        'audience',
        'department_id',
        'hall_id',
        'created_by',
        'published_at',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forDepartment($this->department_id)
            ->merge(ResourceScope::forHall($this->hall_id))
            ->merge(ResourceScope::forOwner($this->created_by));
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
