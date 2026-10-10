<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The extended personal profile a student must complete (spec §6).
 */
class StudentProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id',
        'full_name_certificate',
        'father_name',
        'mother_name',
        'date_of_birth',
        'email',
        'present_address',
        'permanent_address',
        'photo_path',
        'guardian_name',
        'guardian_phone',
        'blood_group',
        'nid_or_birth_reg',
        'is_residential',
        'hall_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_completed_at',
        'locked_fields',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_residential' => 'boolean',
            'profile_completed_at' => 'datetime',
            'locked_fields' => 'array',
        ];
    }
}
