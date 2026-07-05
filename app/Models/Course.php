<?php

namespace App\Models;

use App\Enums\CourseType;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
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

    /** Effective classes per week: explicit override wins; labs default to 1; theory defaults to round(credit_hours). */
    public function getEffectiveWeeklyClassesAttribute(): int
    {
        if ($this->weekly_classes !== null) {
            return $this->weekly_classes;
        }

        return $this->type === CourseType::Lab ? 1 : (int) round($this->credit_hours);
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
