<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\InAppNotification;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationEvents;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Hands a notification to the email pipeline: when it names an email event
 * the central outbox decides (rules, recipients, template, digest, dedupe);
 * otherwise it falls back to the bound {@see ExternalMessenger}.
 */
class ExternalChannel
{
    public function __construct(
        protected ExternalMessenger $messenger,
        protected NotificationEvents $events,
        protected NotificationEventRegistry $registry,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof InAppNotification) {
            return;
        }

        $key = $notification->eventKey();

        if ($key !== null && $this->registry->has($key)) {
            $this->events->emit($key, $notification->emailContext(), $key.':'.($notification->id ?? Str::uuid()), $notifiable);

            return;
        }

        $this->messenger->send($notifiable, $notification->title(), $notification->body(), $notification->url());
    }
}
