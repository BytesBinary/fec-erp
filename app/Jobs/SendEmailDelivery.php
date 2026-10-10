<?php

namespace App\Jobs;

use App\Enums\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\NotificationEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Sends one delivery. Does nothing for a delivery that is not waiting, so a
 * retried job never sends the same email twice.
 */
class SendEmailDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public int $deliveryId)
    {
        $this->tries = (int) config('notifications.delivery_tries');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('notifications.delivery_backoff_seconds');
    }

    public function handle(ExternalMessenger $messenger): void
    {
        $delivery = EmailDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== EmailDeliveryStatus::Queued) {
            return;
        }

        $delivery->increment('attempts');

        try {
            $messenger->sendToAddress($delivery->recipient_email, $delivery->subject, $delivery->body, $delivery->url);
        } catch (Throwable $exception) {
            $delivery->update(['last_error' => mb_substr($exception->getMessage(), 0, 500)]);

            throw $exception;
        }

        $delivery->update(['status' => EmailDeliveryStatus::Sent, 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        $delivery = EmailDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->update(['status' => EmailDeliveryStatus::Failed, 'last_error' => mb_substr($exception->getMessage(), 0, 500)]);

        $recent = EmailDelivery::query()->where('status', EmailDeliveryStatus::Failed->value)->where('updated_at', '>=', now()->subHour())->count();

        if ($recent >= (int) config('notifications.failure_alert_threshold') && Cache::add('notifications.failure_alert', true, now()->addHours(6))) {
            app(NotificationEvents::class)->emit('system.email_delivery_failing', ['count' => $recent, 'error' => $exception->getMessage(), 'link' => url('/email-deliveries')], 'email_failing:'.now()->format('YmdH'));
        }
    }
}
