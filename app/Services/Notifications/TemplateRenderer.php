<?php

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Support\Str;

/**
 * Fills `{placeholders}` of a subject/body. Only the placeholders declared for
 * the event are replaced; anything else disappears, so a template can never
 * reach into model data.
 */
class TemplateRenderer
{
    public function __construct(protected NotificationEventRegistry $registry) {}

    /**
     * @param  array<string, string>  $context
     * @return array{subject: string, body: string}
     */
    public function render(string $eventKey, ?EmailTemplate $template, array $context, string $recipientName, ?string $link = null): array
    {
        $event = $this->registry->get($eventKey);
        $allowed = $this->registry->placeholders($eventKey);

        $values = collect($context)->only($allowed)->all() + [
            'app' => (string) config('app.name'),
            'recipient_name' => $recipientName,
            'link' => $link ?? (string) ($context['link'] ?? url('/')),
        ];

        $fill = fn (string $text): string => preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m): string => in_array($m[1], $allowed, true) ? (string) ($values[$m[1]] ?? '') : '', $text);

        return [
            'subject' => Str::limit(trim(preg_replace('/\s+/', ' ', $fill($template?->subject ?? $event['subject']))), 150, ''),
            'body' => trim($fill($template?->body ?? $event['body'])),
        ];
    }

    /**
     * The registry's sample values, for previews and test emails.
     *
     * @return array<string, string>
     */
    public function sample(string $eventKey): array
    {
        return array_map('strval', $this->registry->get($eventKey)['sample']);
    }
}
