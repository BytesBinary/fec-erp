<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An academic term (e.g. "Spring 2026"). At most one is active.
 */
class Semester extends Model
{
    /** @use HasFactory<\Database\Factories\SemesterFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('starts_on')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
