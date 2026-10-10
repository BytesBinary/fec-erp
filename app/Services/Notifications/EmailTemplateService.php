<?php

namespace App\Services\Notifications;

use App\Exceptions\Domain\ValidationException;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;

/**
 * Admin-edited subjects and bodies. A template may only use the placeholders
 * declared for its event and may not look like it carries a credential.
 */
class EmailTemplateService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected NotificationEventRegistry $registry,
        protected SecretGuard $guard,
    ) {}

    /**
     * @return Collection<int, EmailTemplate>
     */
    public function list(User $actor, ?string $eventKey = null): Collection
    {
        $this->authorizer->authorize($actor, 'notification_rule:view');

        return EmailTemplate::query()->when($eventKey !== null, fn ($query) => $query->where('event_key', $eventKey))->orderBy('event_key')->orderBy('name')->get();
    }

    /**
     * @param  array{event_key: string, name: string, subject: string, body: string}  $data
     */
    public function create(User $actor, array $data): EmailTemplate
    {
        $this->authorizer->authorize($actor, 'email_template:manage');
        $this->validate($data);

        return EmailTemplate::query()->create($data);
    }

    /**
     * @param  array{name?: string, subject?: string, body?: string}  $data
     */
    public function update(User $actor, EmailTemplate $template, array $data): EmailTemplate
    {
        $this->authorizer->authorize($actor, 'email_template:manage');
        $this->validate([...$template->only(['event_key', 'name', 'subject', 'body']), ...$data]);

        $template->update($data);

        return $template->fresh();
    }

    public function delete(User $actor, EmailTemplate $template): void
    {
        $this->authorizer->authorize($actor, 'email_template:manage');

        $template->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data): void
    {
        $this->registry->get((string) $data['event_key']);

        if (trim((string) $data['name']) === '' || trim((string) $data['subject']) === '' || trim((string) $data['body']) === '') {
            throw new ValidationException('Name, subject and body are required.');
        }

        $allowed = $this->registry->placeholders((string) $data['event_key']);

        preg_match_all('/\{([a-z_]+)\}/', $data['subject']."\n".$data['body'], $matches);
        $unknown = array_values(array_diff(array_unique($matches[1]), $allowed));

        if ($unknown !== []) {
            throw new ValidationException('Unknown placeholder(s): {'.implode('}, {', $unknown).'}. Allowed: {'.implode('}, {', $allowed).'}.');
        }

        if (($problem = $this->guard->problemIn($data['subject']."\n".$data['body'])) !== null) {
            throw new ValidationException($problem);
        }
    }
}
