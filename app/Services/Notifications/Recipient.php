<?php

namespace App\Services\Notifications;

use App\Models\User;

/**
 * Someone an event resolves to. `email` is null when the person has no usable
 * address; the delivery is then recorded as skipped so staff can see it.
 */
final readonly class Recipient
{
    public function __construct(public ?User $user, public ?string $email, public string $name) {}

    public function key(): string
    {
        return $this->user !== null ? 'u'.$this->user->getKey() : 'e'.strtolower((string) $this->email);
    }
}
