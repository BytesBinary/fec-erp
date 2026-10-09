<?php

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Pins one of a user's roles to a concrete record, e.g. department_head of
 * department 5. See docs/DECISIONS.md D-007.
 */
class RoleScope extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'scope_id' => 'integer',
        ];
    }
}
