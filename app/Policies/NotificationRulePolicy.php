<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotificationRule;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Answered by the central Authorizer (notification_rule:view).
 */
class NotificationRulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser instanceof User && app(Authorizer::class)->allows($authUser, 'notification_rule:view');
    }

    public function view(AuthUser $authUser, NotificationRule $record): bool
    {
        return $this->viewAny($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, NotificationRule $record): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, NotificationRule $record): bool
    {
        return false;
    }
}
