<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audited write. Rows are append-only: never updated or deleted by the app.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'channel',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'ip',
        'user_agent',
        'integration_id',
        'created_at',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
            'integration_id' => 'integer',
        ];
    }
}
