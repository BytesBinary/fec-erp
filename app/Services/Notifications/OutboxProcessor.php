<?php

namespace App\Services\Notifications;

use App\Enums\EmailDeliveryStatus;
use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns outbox events into email deliveries. Safe to run twice at once and
 * after a crash: rows are locked, and every delivery has a unique dedupe key.
 */
class OutboxProcessor
{
    public function __construct(
        protected NotificationRules $rules,
        protected RecipientResolver $resolver,
        protected TemplateRenderer $renderer,
        protected SecretGuard $guard,
    ) {}

    public function process(): int
    {
        $ids = OutboxEvent::query()
            ->whereNull('processed_at')
            ->where('attempts', '<', (int) config('notifications.outbox_max_attempts'))
            ->orderBy('id')
            ->limit((int) config('notifications.outbox_batch'))
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$processed): void {
                    $event = OutboxEvent::query()->whereKey($id)->whereNull('processed_at')->lockForUpdate()->first();

                    if ($event === null) {
                        return;
                    }

                    $this->handle($event);
                    $event->update(['processed_at' => now(), 'last_error' => null]);
                    $processed++;
                });
            } catch (Throwable $exception) {
                OutboxEvent::query()->whereKey($id)->update(['attempts' => DB::raw('attempts + 1'), 'last_error' => mb_substr($exception->getMessage(), 0, 500)]);
            }
        }

        return $processed;
    }

    protected function handle(OutboxEvent $event): void
    {
        $rule = $this->rules->forEvent($event->event_key);

        if (! $rule->enabled) {
            return;
        }

        foreach ($this->resolver->resolve($rule, $event) as $recipient) {
            $rendered = $this->renderer->render($event->event_key, $rule->template, $event->context, $recipient->name, $event->context['link'] ?? null);
            $problem = $this->guard->problemIn($rendered['subject']."\n".$rendered['body']);

            $status = match (true) {
                $recipient->email === null => EmailDeliveryStatus::Skipped,
                $problem !== null => EmailDeliveryStatus::Blocked,
                $rule->mode === 'digest' => EmailDeliveryStatus::Held,
                default => EmailDeliveryStatus::Queued,
            };

            $delivery = EmailDelivery::query()->firstOrCreate(
                ['dedupe_key' => mb_substr($event->dedupe_key.'|'.$recipient->key(), 0, 191)],
                [
                    'outbox_event_id' => $event->getKey(),
                    'event_key' => $event->event_key,
                    'recipient_user_id' => $recipient->user?->getKey(),
                    'recipient_email' => $recipient->email ?? '',
                    'subject' => $rendered['subject'],
                    'body' => $rendered['body'],
                    'url' => $event->context['link'] ?? null,
                    'status' => $status,
                    'mode' => $rule->mode,
                    'last_error' => match (true) {
                        $recipient->email === null => 'The recipient has no valid email address.',
                        $problem !== null => $problem,
                        default => null,
                    },
                    'queued_at' => now(),
                ],
            );

            if ($delivery->wasRecentlyCreated && $status === EmailDeliveryStatus::Queued) {
                SendEmailDelivery::dispatch($delivery->getKey())->onQueue((string) config('notifications.queue'))->afterCommit();
            }
        }
    }
}
