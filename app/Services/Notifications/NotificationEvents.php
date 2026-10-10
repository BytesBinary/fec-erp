<?php

namespace App\Services\Notifications;

use App\Jobs\ProcessOutbox;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The one door every module uses to say "this happened". Writes the event to
 * the outbox in the caller's database transaction (so a crash cannot lose
 * it) and nudges the outbox job. Whether an email is sent, to whom and with
 * which text is decided later from the admin's rules.
 */
class NotificationEvents
{
    public function __construct(protected NotificationEventRegistry $registry, protected NotificationRules $rules) {}

    /**
     * @param  array<string, mixed>  $context  only the event's declared placeholders are kept
     * @param  string|null  $dedupe  same key = same event; a repeat is ignored. Null never dedupes.
     * @param  list<string>  $emails  extra addresses for the `context_emails` recipient kind
     */
    public function emit(string $key, array $context = [], ?string $dedupe = null, ?User $user = null, ?int $departmentId = null, ?int $hallId = null, array $emails = []): ?OutboxEvent
    {
        $this->registry->get($key);

        if (! config('notifications.enabled') || ! $this->rules->forEvent($key)->enabled) {
            return null;
        }

        $clean = collect($context)
            ->only($this->registry->placeholders($key))
            ->map(fn (mixed $value): string => Str::limit(is_scalar($value) ? (string) $value : (string) json_encode($value), 500, '…'))
            ->all();

        if ($emails !== []) {
            $clean['__emails'] = array_values(array_unique(array_filter($emails)));
        }

        $event = OutboxEvent::query()->firstOrCreate(
            ['dedupe_key' => Str::limit($dedupe ?? $key.':'.Str::uuid(), 191, '')],
            [
                'event_key' => $key,
                'context' => $clean,
                'affected_user_id' => $user?->getKey(),
                'department_id' => $departmentId,
                'hall_id' => $hallId,
                'occurred_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessOutbox::dispatch()->onQueue((string) config('notifications.queue'))->afterCommit();
        }

        return $event;
    }
}
