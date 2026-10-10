<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ResultPull;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Pulls are system records: staff can list and retry them, nobody edits them.
 */
class ResultPullPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser instanceof User && app(Authorizer::class)->allows($authUser, 'result_pull:list');
    }

    public function view(AuthUser $authUser, ResultPull $pull): bool
    {
        return $authUser instanceof User && app(Authorizer::class)->allows($authUser, 'result_pull:list', $pull);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, ResultPull $pull): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, ResultPull $pull): bool
    {
        return false;
    }
}
