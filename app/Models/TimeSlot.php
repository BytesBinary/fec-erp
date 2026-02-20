<?php

namespace App\Models;

use App\Enums\CourseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeSlot extends Model
{
    /** @use HasFactory<\Database\Factories\TimeSlotFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'type',
        'sort_order',
    ];

    public function routineSlots(): HasMany
    {
        return $this->hasMany(RoutineSlot::class);
    }

    public function getDisplayAttribute(): string
    {
        return "{$this->name} ({$this->start_time} – {$this->end_time})";
    }

    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
            'sort_order' => 'integer',
        ];
    }
}
