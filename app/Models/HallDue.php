<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallDue extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = ['student_id', 'hall_id', 'description', 'amount', 'settled_at'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * @param  Builder<HallDue>  $query
     * @return Builder<HallDue>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('settled_at');
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forHall($this->hall_id)->merge(ResourceScope::forOwner($this->student?->user_id));
    }

    protected function casts(): array
    {
        return ['amount' => 'float', 'settled_at' => 'datetime'];
    }
}
