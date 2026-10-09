<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\BatchFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'batch_number',
        'session',
        'current_semester',
        'is_active',
        'is_archived',
    ];

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function routineSlots(): HasMany
    {
        return $this->hasMany(RoutineSlot::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return "Batch {$this->batch_number} ({$this->session})";
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forDepartment($this->department_id);
    }

    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'current_semester' => 'integer',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }
}
