<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TOTP state for one user. The secret is encrypted at rest; `enabled_at` is
 * null while setup is pending confirmation.
 */
class UserMfa extends Model
{
    protected $table = 'user_mfa';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'totp_secret_encrypted',
        'enabled_at',
        'last_used_step',
        'failed_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'totp_secret_encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    protected function casts(): array
    {
        return [
            'totp_secret_encrypted' => 'encrypted',
            'enabled_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_used_step' => 'integer',
            'failed_attempts' => 'integer',
        ];
    }
}
