<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser/OS fingerprint the user has logged in from before (new-device
 * alerts) and, optionally, a "trust this device" token that skips the 2FA
 * challenge (spec §3A.2).
 */
class KnownDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_hash',
        'label',
        'trusted_token_hash',
        'trusted_until',
        'first_seen_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'trusted_until' => 'datetime',
            'first_seen_at' => 'datetime',
        ];
    }
}
