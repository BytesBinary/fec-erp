<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\TeacherFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'designation_id',
        'short_name',
        'employee_id',
        'joining_date',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function routineSlots(): HasMany
    {
        return $this->hasMany(RoutineSlot::class);
    }

    public function authorizationScope(): ResourceScope
    {
        $courseIds = $this->relationLoaded('courses') ? $this->courses->modelKeys() : $this->courses()->pluck('courses.id')->all();

        return ResourceScope::forOwner($this->user_id)
            ->merge(ResourceScope::forDepartment($this->department_id))
            ->merge(new ResourceScope(courseIds: array_map('intval', $courseIds)));
    }

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
        ];
    }
}
