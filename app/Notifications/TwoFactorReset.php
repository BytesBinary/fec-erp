<?php

namespace App\Notifications;

class TwoFactorReset extends InAppNotification
{
    public function __construct(public string $reason) {}

    public function eventKey(): ?string
    {
        return 'security.two_factor_reset_by_admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function emailContext(): array
    {
        return ['reason' => $this->reason, 'link' => url('/two-factor/setup')];
    }

    public function title(): string
    {
        return __('erp.security.reset_title');
    }

    public function body(): string
    {
        return __('erp.security.reset_body', ['reason' => $this->reason]);
    }

    public function color(): string
    {
        return 'warning';
    }
}
