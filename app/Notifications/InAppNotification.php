<?php

namespace App\Notifications;

use App\Notifications\Channels\ExternalChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Base for notifications shown in the panel's notification bell (always on)
 * and mirrored to the pluggable e-mail/SMS driver.
 */
abstract class InAppNotification extends Notification
{
    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * Optional deep link opened by the notification's action button.
     */
    public function url(): ?string
    {
        return null;
    }

    public function actionLabel(): string
    {
        return __('erp.notifications.open');
    }

    public function color(): string
    {
        return 'info';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', ExternalChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->color($this->color());

        if ($this->url() !== null) {
            $notification->actions([
                Action::make('open')->label($this->actionLabel())->url($this->url())->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
