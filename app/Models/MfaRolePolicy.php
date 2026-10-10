<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Per-role switch: users holding a role with `required = true` must set up
 * 2FA before using the panel (spec §3A.2). Off for every role by default.
 */
class MfaRolePolicy extends Model
{
    protected $primaryKey = 'role_id';

    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'required',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
