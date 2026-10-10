<?php

namespace App\Models;

use App\Enums\AttemptType;
use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = [
        'student_id',
        'course_offering_id',
        'attempt_type',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forOwner($this->student?->user_id)
            ->merge(ResourceScope::forCourse($this->offering?->course_id, $this->offering?->course?->department_id))
            ->merge(ResourceScope::forDepartment($this->student?->department_id));
    }

    protected function casts(): array
    {
        return [
            'attempt_type' => AttemptType::class,
        ];
    }
}
