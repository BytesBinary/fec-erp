<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `role`: user, assistant, tool (result of a tool call) or action (a write
 * waiting for the user's confirmation, see AssistantService).
 */
class AssistantMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'role', 'content', 'tool_calls'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    protected function casts(): array
    {
        return ['tool_calls' => 'array'];
    }
}
