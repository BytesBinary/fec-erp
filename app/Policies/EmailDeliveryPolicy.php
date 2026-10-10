<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailDelivery;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Answered by the central Authorizer (email_delivery:view).
 */
class EmailDeliveryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser instanceof User && app(Authorizer::class)->allows($authUser, 'email_delivery:view');
    }

    public function view(AuthUser $authUser, EmailDelivery $record): bool
    {
        return $this->viewAny($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, EmailDelivery $record): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, EmailDelivery $record): bool
    {
        return false;
    }
}
