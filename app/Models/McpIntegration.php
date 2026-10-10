<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI client connected to a user's account (spec §4.5). Only a SHA-256 of
 * the bearer token is stored; the plain token is shown once at creation.
 */
class McpIntegration extends Model
{
    public const CLIENTS = ['claude_desktop', 'claude_code', 'cursor', 'vscode', 'other'];

    protected $fillable = [
        'user_id', 'name', 'client_type', 'access_level', 'token_hash', 'token_prefix', 'expires_at', 'first_connected_at',
        'last_used_at', 'last_used_ip', 'known_ips', 'expiry_notified_at', 'revoked_at', 'revoked_by', 'revoked_reason',
    ];

    protected $hidden = ['token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Not revoked and not expired.
     *
     * @param  Builder<McpIntegration>  $query
     * @return Builder<McpIntegration>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isReadOnly(): bool
    {
        return $this->access_level === 'read_only';
    }

    public function status(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            $this->expires_at !== null && $this->expires_at->lte(now()->addDays(7)) => 'expiring_soon',
            default => 'active',
        };
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'first_connected_at' => 'datetime',
            'last_used_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
            'revoked_at' => 'datetime',
            'known_ips' => 'array',
        ];
    }
}
