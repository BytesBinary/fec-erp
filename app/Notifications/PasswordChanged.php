<?php

namespace App\Notifications;

use App\Filament\Pages\Security\Devices;

/**
 * Tells the user their password changed and how many other devices were signed out (spec §3A.1).
 */
class PasswordChanged extends InAppNotification
{
    public function __construct(public int $signedOutDevices) {}

    public function title(): string
    {
        return __('erp.security.password_changed_title');
    }

    public function body(): string
    {
        return __('erp.security.password_changed_body', ['count' => $this->signedOutDevices]);
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
