<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallResidency extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = ['student_id', 'hall_id', 'room', 'assigned_on', 'ended_on'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * @param  Builder<HallResidency>  $query
     * @return Builder<HallResidency>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forHall($this->hall_id)->merge(ResourceScope::forDepartment($this->student?->department_id))->merge(ResourceScope::forOwner($this->student?->user_id));
    }

    protected function casts(): array
    {
        return ['assigned_on' => 'date', 'ended_on' => 'date'];
    }
}
