<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Server-side record of one browser session (spec §3A.1). `session_hash` is
 * the sha256 of the Laravel session id, so the raw id is never stored.
 */
class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_hash',
        'device_label',
        'device_type',
        'browser',
        'os',
        'ip',
        'location',
        'remember',
        'mfa_passed_at',
        'last_active_at',
        'expires_at',
        'trusted_until',
        'trusted_token_hash',
        'revoked_at',
        'revoked_by',
        'revoked_reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Sessions that are neither revoked nor expired.
     *
     * @param  Builder<UserSession>  $query
     * @return Builder<UserSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isTrusted(): bool
    {
        return $this->trusted_until !== null && $this->trusted_until->isFuture();
    }

    protected function casts(): array
    {
        return [
            'remember' => 'boolean',
            'mfa_passed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'expires_at' => 'datetime',
            'trusted_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
