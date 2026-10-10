<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course delivered in a semester (course × semester × section).
 */
class CourseOffering extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = [
        'course_id',
        'semester_id',
        'section',
        'teacher_id',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forCourse($this->course_id, $this->course?->department_id);
    }
}
