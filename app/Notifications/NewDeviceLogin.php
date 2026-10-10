<?php

namespace App\Notifications;

use App\Filament\Pages\Security\Devices;

class NewDeviceLogin extends InAppNotification
{
    public function __construct(public string $deviceLabel, public string $ip) {}

    public function eventKey(): ?string
    {
        return 'security.new_device_login';
    }

    /**
     * @return array<string, mixed>
     */
    public function emailContext(): array
    {
        return ['device' => $this->deviceLabel, 'ip' => $this->ip, 'time' => now()->format('d M Y H:i'), 'link' => $this->url()];
    }

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
