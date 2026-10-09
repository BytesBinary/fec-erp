<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\DelegatesToAuthorizer;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Answered by the central Authorizer (`batch:*` permissions, scope-aware).
 */
class BatchPolicy
{
    use DelegatesToAuthorizer, HandlesAuthorization;

    protected function resourceKey(): string
    {
        return 'batch';
    }
}
