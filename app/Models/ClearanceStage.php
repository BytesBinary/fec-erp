<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * One configurable step of the clearance chain (spec §8.2).
 * `scope_rule`: `hall` (provost of the student's hall), `department`
 * (head of the student's department), `global` (any holder of the role).
 */
class ClearanceStage extends Model
{
    use Auditable;

    protected $fillable = ['key', 'label', 'order', 'approver_role_id', 'scope_rule', 'skippable', 'active'];

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    /**
     * @param  Builder<ClearanceStage>  $query
     * @return Builder<ClearanceStage>
     */
    public function scopeInChain(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('order')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['order' => 'integer', 'skippable' => 'boolean', 'active' => 'boolean'];
    }
}
