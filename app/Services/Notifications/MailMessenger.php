<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Notifications\Contracts\ExternalMessenger;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the email through the configured Laravel mailer (MAIL_* settings).
 * Plain text only: nothing in an email is rendered as HTML.
 */
class MailMessenger implements ExternalMessenger
{
    public function send(User $recipient, string $subject, string $body, ?string $url = null): void
    {
        $this->sendToAddress((string) $recipient->email, $subject, $body, $url);
    }

    public function sendToAddress(string $email, string $subject, string $body, ?string $url = null): void
    {
        Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
    }
}
