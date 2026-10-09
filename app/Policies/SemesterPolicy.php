<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\DelegatesToAuthorizer;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Answered by the central Authorizer (`semester:*` permissions, scope-aware).
 */
class SemesterPolicy
{
    use DelegatesToAuthorizer, HandlesAuthorization;

    protected function resourceKey(): string
    {
        return 'semester';
    }
}
