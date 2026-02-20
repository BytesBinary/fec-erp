<?php

namespace App\Models;

use App\Enums\CourseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'semester_number',
        'type',
        'code',
        'version',
        'name',
        'credit_hours',
        'weekly_classes',
        'description',
        'is_active',
    ];

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }

    /** Effective classes per week (default 1 if not explicitly set). */
    public function getEffectiveWeeklyClassesAttribute(): int
    {
        return $this->weekly_classes ?? 1;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    public function routineSlots(): HasMany
    {
        return $this->hasMany(RoutineSlot::class);
    }

    protected function casts(): array
    {
        return [
            'semester_number' => 'integer',
            'version' => 'integer',
            'credit_hours' => 'float',
            'weekly_classes' => 'integer',
            'type' => CourseType::class,
            'is_active' => 'boolean',
        ];
    }
}
