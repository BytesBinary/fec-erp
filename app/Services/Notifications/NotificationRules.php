<?php

namespace App\Services\Notifications;

use App\Models\NotificationRule;

/**
 * The per-event rows behind the admin screen. A row is created from the
 * registry defaults the first time it is needed; an admin's edits are never
 * overwritten by a later sync.
 */
class NotificationRules
{
    public function __construct(protected NotificationEventRegistry $registry) {}

    public function forEvent(string $key): NotificationRule
    {
        $event = $this->registry->get($key);

        return NotificationRule::query()->firstOrCreate(['event_key' => $key], [
            'category' => $event['category'],
            'enabled' => $event['enabled'],
            'mode' => $event['mode'],
            'recipients' => $event['recipients'],
        ]);
    }

    /**
     * Creates the missing rows. Returns how many were added.
     */
    public function sync(): int
    {
        $existing = NotificationRule::query()->pluck('event_key')->all();
        $added = 0;

        foreach (array_keys($this->registry->all()) as $key) {
            if (! in_array($key, $existing, true)) {
                $this->forEvent($key);
                $added++;
            }
        }

        return $added;
    }
}
