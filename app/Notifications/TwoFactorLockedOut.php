<?php

namespace App\Notifications;

use App\Filament\Pages\Security\Devices;

class TwoFactorLockedOut extends InAppNotification
{
    public function __construct(public int $minutes) {}

    public function title(): string
    {
        return __('erp.security.locked_out_title');
    }

    public function body(): string
    {
        return __('erp.security.locked_out_body', ['minutes' => $this->minutes]);
    }

    public function url(): ?string
    {
        return Devices::getUrl();
    }

    public function actionLabel(): string
    {
        return __('erp.security.review_devices');
    }

    public function color(): string
    {
        return 'danger';
    }
}
