<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\DelegatesToAuthorizer;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Answered by the central Authorizer (`designation:*` permissions, scope-aware).
 */
class DesignationPolicy
{
    use DelegatesToAuthorizer, HandlesAuthorization;

    protected function resourceKey(): string
    {
        return 'designation';
    }
}
