<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Notifications\Contracts\ExternalMessenger;
use Illuminate\Support\Facades\Log;

class LogMessenger implements ExternalMessenger
{
    public function send(User $recipient, string $subject, string $body, ?string $url = null): void
    {
        Log::info('external-notification', [
            'user_id' => $recipient->getKey(),
            'subject' => $subject,
            'body' => $body,
            'url' => $url,
        ]);
    }

    public function sendToAddress(string $email, string $subject, string $body, ?string $url = null): void
    {
        Log::info('external-notification', ['email' => $email, 'subject' => $subject, 'body' => $body, 'url' => $url]);
    }
}
