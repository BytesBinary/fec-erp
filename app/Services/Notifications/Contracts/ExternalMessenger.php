<?php

namespace App\Services\Notifications\Contracts;

use App\Models\User;

/**
 * Pluggable e-mail / SMS delivery (docs/DECISIONS.md). The codebase has no
 * mail or SMS gateway yet, so the bound default only logs; a real driver can
 * be swapped in through the container without touching the notifications.
 */
interface ExternalMessenger
{
    public function send(User $recipient, string $subject, string $body, ?string $url = null): void;

    /**
     * Sends to a plain address (used by the email pipeline and for addresses
     * that do not belong to a user, e.g. the old address after an email change).
     */
    public function sendToAddress(string $email, string $subject, string $body, ?string $url = null): void;
}
