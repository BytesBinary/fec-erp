<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether an event sends an email, to whom, how (now or in the daily digest)
 * and with which template. One row per event in `config/notification_events.php`.
 */
class NotificationRule extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationRuleFactory> */
    use HasFactory;

    protected $fillable = ['event_key', 'category', 'enabled', 'mode', 'recipients', 'email_template_id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'recipients' => 'array'];
    }
}
