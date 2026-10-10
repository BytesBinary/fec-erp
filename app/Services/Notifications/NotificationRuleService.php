<?php

namespace App\Services\Notifications;

use App\Exceptions\Domain\ValidationException;
use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * The admin side of the event rules: list, change, reset.
 */
class NotificationRuleService
{
    public const KINDS = ['affected_user', 'department_head', 'hall_provost', 'context_emails'];

    public function __construct(
        protected Authorizer $authorizer,
        protected NotificationRules $rules,
        protected NotificationEventRegistry $registry,
        protected AuditLogger $audit,
    ) {}

    /**
     * Every event with its current rule, grouped by category.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function list(User $actor): Collection
    {
        $this->authorizer->authorize($actor, 'notification_rule:view');
        $this->rules->sync();

        $rules = NotificationRule::query()->with('template')->get()->keyBy('event_key');

        return collect($this->registry->all())->map(fn (array $event, string $key): array => [
            'event_key' => $key,
            'label' => $event['label'],
            'category' => $event['category'],
            'enabled' => $rules[$key]->enabled,
            'mode' => $rules[$key]->mode,
            'recipients' => $rules[$key]->recipients,
            'template_id' => $rules[$key]->email_template_id,
            'template' => $rules[$key]->template?->name,
            'placeholders' => $this->registry->placeholders($key),
            'sensitive' => $event['sensitive'],
        ])->groupBy('category');
    }

    /**
     * @param  array{enabled?: bool, mode?: string, recipients?: list<string>, email_template_id?: int|null}  $data
     */
    public function update(User $actor, string $eventKey, array $data): NotificationRule
    {
        $this->authorizer->authorize($actor, 'notification_rule:manage');
        $this->registry->get($eventKey);

        $rule = $this->rules->forEvent($eventKey);
        $before = $rule->only(['enabled', 'mode', 'recipients', 'email_template_id']);
        $changes = [];

        if (array_key_exists('enabled', $data)) {
            $changes['enabled'] = (bool) $data['enabled'];
        }

        if (array_key_exists('mode', $data)) {
            if (! in_array($data['mode'], ['immediate', 'digest'], true)) {
                throw new ValidationException('The mode must be "immediate" or "digest".');
            }

            $changes['mode'] = $data['mode'];
        }

        if (array_key_exists('recipients', $data)) {
            $changes['recipients'] = $this->validRecipients((array) $data['recipients']);
        }

        if (array_key_exists('email_template_id', $data)) {
            $templateId = $data['email_template_id'];

            if ($templateId !== null && ! EmailTemplate::query()->where('event_key', $eventKey)->whereKey($templateId)->exists()) {
                throw new ValidationException('That template belongs to a different event.');
            }

            $changes['email_template_id'] = $templateId;
        }

        $rule->update($changes);
        $this->audit->record('notification.rule_changed', $rule, $before, $rule->only(['enabled', 'mode', 'recipients', 'email_template_id']), $actor);

        return $rule->fresh();
    }

    /**
     * Back to the registry defaults.
     */
    public function reset(User $actor, string $eventKey): NotificationRule
    {
        $event = $this->registry->get($eventKey);

        return $this->update($actor, $eventKey, ['enabled' => $event['enabled'], 'mode' => $event['mode'], 'recipients' => $event['recipients'], 'email_template_id' => null]);
    }

    /**
     * @param  list<string>  $recipients
     * @return list<string>
     */
    protected function validRecipients(array $recipients): array
    {
        $roles = Role::query()->pluck('name')->all();

        foreach ($recipients as $kind) {
            $valid = in_array($kind, self::KINDS, true) || (str_starts_with((string) $kind, 'role:') && in_array(substr((string) $kind, 5), $roles, true));

            if (! $valid) {
                throw new ValidationException("Unknown recipient \"{$kind}\".");
            }
        }

        return array_values(array_unique($recipients));
    }
}
