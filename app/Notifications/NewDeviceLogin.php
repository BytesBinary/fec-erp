<?php

namespace App\Notifications;

use App\Filament\Pages\Security\Devices;

class NewDeviceLogin extends InAppNotification
{
    public function __construct(public string $deviceLabel, public string $ip) {}

    public function title(): string
    {
        return __('erp.security.new_device_title');
    }

    public function body(): string
    {
        return __('erp.security.new_device_body', ['device' => $this->deviceLabel, 'ip' => $this->ip]);
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
        return 'warning';
    }
}
