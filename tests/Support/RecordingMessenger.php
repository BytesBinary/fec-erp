<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\Notifications\Contracts\ExternalMessenger;
use RuntimeException;

/**
 * Stand-in for the mail sender: records what would be sent and can be told to fail.
 */
class RecordingMessenger implements ExternalMessenger
{
    /** @var list<array{email: string, subject: string, body: string}> */
    public array $sent = [];

    public int $failNext = 0;

    public function send(User $recipient, string $subject, string $body, ?string $url = null): void
    {
        $this->sendToAddress((string) $recipient->email, $subject, $body, $url);
    }

    public function sendToAddress(string $email, string $subject, string $body, ?string $url = null): void
    {
        if ($this->failNext > 0) {
            $this->failNext--;

            throw new RuntimeException('SMTP connection refused');
        }

        $this->sent[] = ['email' => $email, 'subject' => $subject, 'body' => $body];
    }
}
