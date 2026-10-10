<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\EmailDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'outbox_event_id', 'event_key', 'recipient_user_id', 'recipient_email', 'subject', 'body', 'url',
        'status', 'mode', 'attempts', 'last_error', 'dedupe_key', 'is_test', 'digest_delivery_id', 'queued_at', 'sent_at',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    protected function casts(): array
    {
        return ['status' => EmailDeliveryStatus::class, 'is_test' => 'boolean', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
