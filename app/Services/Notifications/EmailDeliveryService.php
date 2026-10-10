<?php

namespace App\Services\Notifications;

use App\Enums\EmailDeliveryStatus;
use App\Exceptions\Domain\ValidationException;
use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Delivery history for the admin screen and MCP: list, counts, retry, test
 * email, preview, and the daily digest.
 */
class EmailDeliveryService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected TemplateRenderer $renderer,
        protected NotificationEventRegistry $registry,
        protected SecretGuard $guard,
    ) {}

    /**
     * @return Builder<EmailDelivery>
     */
    public function query(User $actor): Builder
    {
        $this->authorizer->authorize($actor, 'email_delivery:view');

        return EmailDelivery::query()->with('recipient')->latest('id');
    }

    /**
     * @return array<string, int>
     */
    public function counts(User $actor): array
    {
        $this->authorizer->authorize($actor, 'email_delivery:view');

        $counts = EmailDelivery::query()->get()->countBy(fn (EmailDelivery $delivery): string => $delivery->status->value);

        return collect(EmailDeliveryStatus::cases())->mapWithKeys(fn (EmailDeliveryStatus $status): array => [$status->value => (int) $counts->get($status->value, 0)])->all();
    }

    public function retry(User $actor, EmailDelivery $delivery): EmailDelivery
    {
        $this->authorizer->authorize($actor, 'email_delivery:retry');

        if (! $delivery->status->canRetry()) {
            throw new ValidationException('Only a failed, skipped or blocked email can be retried (this one is "'.$delivery->status->value.'").');
        }

        $this->requeue($delivery);

        return $delivery->fresh();
    }

    /**
     * Retries every failed email.
     */
    public function retryFailed(User $actor): int
    {
        $this->authorizer->authorize($actor, 'email_delivery:retry');

        $deliveries = EmailDelivery::query()->where('status', EmailDeliveryStatus::Failed->value)->get();
        $deliveries->each(fn (EmailDelivery $delivery) => $this->requeue($delivery));

        return $deliveries->count();
    }

    /**
     * What an email for this event would look like (sample values). A draft
     * subject/body can be previewed before it is saved as a template.
     *
     * @param  array{subject?: string, body?: string}|null  $draft
     * @return array{subject: string, body: string, warning: ?string}
     */
    public function preview(User $actor, string $eventKey, ?int $templateId = null, ?array $draft = null): array
    {
        $this->authorizer->authorize($actor, 'notification_rule:view');

        $template = $draft !== null
            ? new EmailTemplate(['subject' => (string) ($draft['subject'] ?? ''), 'body' => (string) ($draft['body'] ?? '')])
            : ($templateId !== null ? EmailTemplate::query()->where('event_key', $eventKey)->find($templateId) : null);

        $rendered = $this->renderer->render($eventKey, $template, $this->renderer->sample($eventKey), $actor->name, url('/'));

        return $rendered + ['warning' => $this->guard->problemIn($rendered['subject']."\n".$rendered['body'])];
    }

    /**
     * Sends the sample email of an event to the acting admin only.
     */
    public function sendTest(User $actor, string $eventKey, ?int $templateId = null): EmailDelivery
    {
        $this->authorizer->authorize($actor, 'notification_rule:manage');

        $preview = $this->preview($actor, $eventKey, $templateId);

        if ($preview['warning'] !== null) {
            throw new ValidationException($preview['warning']);
        }

        if (filter_var($actor->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Your account has no valid email address to send the test to.');
        }

        $delivery = EmailDelivery::query()->create([
            'event_key' => $eventKey,
            'recipient_user_id' => $actor->getKey(),
            'recipient_email' => $actor->email,
            'subject' => '[TEST] '.$preview['subject'],
            'body' => $preview['body'],
            'url' => url('/'),
            'status' => EmailDeliveryStatus::Queued,
            'mode' => 'immediate',
            'dedupe_key' => 'test:'.Str::uuid(),
            'is_test' => true,
            'queued_at' => now(),
        ]);

        SendEmailDelivery::dispatch($delivery->getKey())->onQueue((string) config('notifications.queue'))->afterCommit();

        return $delivery->fresh();
    }

    /**
     * Combines every held email of each recipient into one digest email.
     * Returns how many digest emails were created.
     */
    public function sendDigests(): int
    {
        $created = 0;

        EmailDelivery::query()
            ->where('status', EmailDeliveryStatus::Held->value)
            ->get()
            ->groupBy('recipient_email')
            ->each(function ($items, string $email) use (&$created): void {
                $first = $items->first();
                $lines = $items->take((int) config('notifications.digest_max_items'))->map(fn (EmailDelivery $item): string => '• '.$item->subject)->implode("\n");
                $more = $items->count() - (int) config('notifications.digest_max_items');

                $digest = EmailDelivery::query()->create([
                    'event_key' => 'digest',
                    'recipient_user_id' => $first->recipient_user_id,
                    'recipient_email' => $email,
                    'subject' => config('app.name').': '.$items->count().' notification(s)',
                    'body' => "You have {$items->count()} new notification(s):\n\n{$lines}".($more > 0 ? "\n…and {$more} more." : '')."\n\nOpen: ".url('/'),
                    'url' => url('/'),
                    'status' => EmailDeliveryStatus::Queued,
                    'mode' => 'digest',
                    'dedupe_key' => 'digest:'.Str::uuid(),
                    'queued_at' => now(),
                ]);

                EmailDelivery::query()->whereIn('id', $items->pluck('id'))->update(['status' => EmailDeliveryStatus::Digested->value, 'digest_delivery_id' => $digest->getKey()]);
                SendEmailDelivery::dispatch($digest->getKey())->onQueue((string) config('notifications.queue'))->afterCommit();
                $created++;
            });

        return $created;
    }

    protected function requeue(EmailDelivery $delivery): void
    {
        $delivery->update(['status' => EmailDeliveryStatus::Queued, 'last_error' => null, 'queued_at' => now()]);

        SendEmailDelivery::dispatch($delivery->getKey())->onQueue((string) config('notifications.queue'))->afterCommit();
    }
}
