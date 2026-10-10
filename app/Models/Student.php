<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'program_id',
        'batch_id',
        'admission_year',
        'roll_number',
        'registration_number',
        'current_semester',
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * The year the student was admitted: the entered value, else the start
     * year of the batch session ("2022-2023" → 2022).
     */
    public function admissionYear(): ?int
    {
        if ($this->admission_year !== null) {
            return (int) $this->admission_year;
        }

        $session = (string) $this->batch?->session;

        return preg_match('/^(\d{4})/', $session, $match) === 1 ? (int) $match[1] : null;
    }

    public function portalResults(): HasMany
    {
        return $this->hasMany(PortalResult::class)->orderBy('course_code')->orderBy('portal_exam_id');
    }

    public function resultPulls(): HasMany
    {
        return $this->hasMany(ResultPull::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forOwner($this->user_id)
            ->merge(ResourceScope::forDepartment($this->department_id));
    }

    protected function casts(): array
    {
        return [
            'current_semester' => 'integer',
        ];
    }
}
