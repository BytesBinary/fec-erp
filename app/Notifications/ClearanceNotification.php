<?php

namespace App\Notifications;

/**
 * Generic clearance notification (title, body, optional deep link).
 */
class ClearanceNotification extends InAppNotification
{
    public function __construct(
        protected string $heading,
        protected string $message,
        protected ?string $link = null,
        protected string $tone = 'info',
        protected ?string $event = null,
    ) {}

    public function eventKey(): ?string
    {
        return $this->event;
    }

    public function title(): string
    {
        return $this->heading;
    }

    public function body(): string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->link;
    }

    public function color(): string
    {
        return $this->tone;
    }
}
