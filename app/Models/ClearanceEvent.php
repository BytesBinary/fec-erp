<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['clearance_request_id', 'from_status', 'to_status', 'actor_user_id', 'channel', 'note'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['channel' => Channel::class];
    }
}
