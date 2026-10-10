<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\InAppNotification;
use App\Services\Notifications\Contracts\ExternalMessenger;
use Illuminate\Notifications\Notification;

/**
 * Hands the notification's title/body to the bound {@see ExternalMessenger}.
 */
class ExternalChannel
{
    public function __construct(protected ExternalMessenger $messenger) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof InAppNotification) {
            return;
        }

        $this->messenger->send($notifiable, $notification->title(), $notification->body(), $notification->url());
    }
}
