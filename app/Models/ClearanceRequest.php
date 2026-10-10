<?php

namespace App\Models;

use App\Enums\ClearanceStatus;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's clearance request. All state changes go through
 * {@see \App\Services\Clearance\ClearanceService}; `version` is the
 * optimistic lock.
 */
class ClearanceRequest extends Model implements HasAuthorizationScope
{
    protected $fillable = [
        'request_no', 'verify_code', 'student_id', 'status', 'current_stage_id', 'submitted_at', 'ready_at', 'printed_at',
        'collected_at', 'collected_by', 'id_verified', 'cancelled_at', 'cancel_reason', 'version', 'chain_hash', 'last_reminded_at',
    ];

    protected $hidden = ['verify_code'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(ClearanceStage::class, 'current_stage_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClearanceApproval::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClearanceEvent::class)->orderBy('id');
    }

    public function prints(): HasMany
    {
        return $this->hasMany(ClearancePrint::class)->orderBy('id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function authorizationScope(): ResourceScope
    {
        $residency = HallResidency::query()->current()->where('student_id', $this->student_id)->value('hall_id');

        return ResourceScope::forOwner($this->student?->user_id)
            ->merge(ResourceScope::forDepartment($this->student?->department_id))
            ->merge(ResourceScope::forHall($residency));
    }

    protected function casts(): array
    {
        return [
            'status' => ClearanceStatus::class,
            'submitted_at' => 'datetime',
            'ready_at' => 'datetime',
            'printed_at' => 'datetime',
            'collected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'id_verified' => 'boolean',
            'version' => 'integer',
        ];
    }
}
