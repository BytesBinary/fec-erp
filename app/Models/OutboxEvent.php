<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An event written in the same database transaction as the change that caused
 * it, so a crash cannot lose it. Processed into deliveries by the outbox job.
 */
class OutboxEvent extends Model
{
    /** @use HasFactory<\Database\Factories\OutboxEventFactory> */
    use HasFactory;

    protected $fillable = ['event_key', 'dedupe_key', 'context', 'affected_user_id', 'department_id', 'hall_id', 'occurred_at', 'processed_at', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
