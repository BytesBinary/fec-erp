<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineSlot extends Model
{
    /** @use HasFactory<\Database\Factories\RoutineSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'batch_id',
        'semester_number',
        'day_of_week',
        'time_slot_id',
        'course_id',
        'teacher_id',
    ];

    /** @var array<int, string> */
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'semester_number' => 'integer',
        ];
    }
}
