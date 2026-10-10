<?php

namespace App\Services\Notifications;

use InvalidArgumentException;

/**
 * Read access to `config/notification_events.php`.
 */
class NotificationEventRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('notification_events.events');
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown notification event \"{$key}\".");
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        return config('notification_events.categories');
    }

    /**
     * Every placeholder an event's template may use.
     *
     * @return list<string>
     */
    public function placeholders(string $key): array
    {
        return array_values(array_unique([...$this->get($key)['placeholders'], 'app', 'recipient_name', 'link']));
    }
}
