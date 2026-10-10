<?php

namespace App\Notifications;

class TwoFactorReset extends InAppNotification
{
    public function __construct(public string $reason) {}

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
