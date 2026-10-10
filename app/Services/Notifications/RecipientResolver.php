<?php

namespace App\Services\Notifications;

use App\Enums\ScopeType;
use App\Models\NotificationRule;
use App\Models\OutboxEvent;
use App\Models\RoleScope;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns a rule's recipient kinds into people, respecting department and hall
 * boundaries: a department head only receives events of their own department.
 */
class RecipientResolver
{
    /**
     * @return Collection<string, Recipient>
     */
    public function resolve(NotificationRule $rule, OutboxEvent $event): Collection
    {
        $recipients = collect();

        foreach ($rule->recipients as $kind) {
            foreach ($this->forKind((string) $kind, $event) as $recipient) {
                $recipients->put($recipient->key(), $recipient);
            }
        }

        return $recipients;
    }

    /**
     * @return list<Recipient>
     */
    protected function forKind(string $kind, OutboxEvent $event): array
    {
        return match (true) {
            $kind === 'affected_user' => $this->users(User::query()->whereKey($event->affected_user_id), includeInactive: $event->event_key === 'security.account_deactivated'),
            $kind === 'department_head' => $event->department_id === null ? [] : $this->scoped('department_head', ScopeType::Department, $event->department_id),
            $kind === 'hall_provost' => $event->hall_id === null ? [] : $this->scoped('hall_provost', ScopeType::Hall, $event->hall_id),
            $kind === 'context_emails' => array_map(fn (string $email): Recipient => new Recipient(null, $this->valid($email), $email), (array) ($event->context['__emails'] ?? [])),
            str_starts_with($kind, 'role:') => $this->users(User::role(substr($kind, 5))),
            default => [],
        };
    }

    /**
     * @return list<Recipient>
     */
    protected function scoped(string $role, ScopeType $type, int $scopeId): array
    {
        $userIds = RoleScope::query()
            ->where('scope_type', $type->value)
            ->where('scope_id', $scopeId)
            ->whereHas('role', fn ($query) => $query->where('name', $role))
            ->pluck('user_id');

        return $this->users(User::query()->whereIn('id', $userIds));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @param  bool  $includeInactive  only for telling someone their own account was deactivated
     * @return list<Recipient>
     */
    protected function users($query, bool $includeInactive = false): array
    {
        return $query->when(! $includeInactive, fn ($inner) => $inner->where('is_active', true))->get()->map(fn (User $user): Recipient => new Recipient($user, $this->valid($user->email), $user->name))->all();
    }

    protected function valid(?string $email): ?string
    {
        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
