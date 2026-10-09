<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\DelegatesToAuthorizer;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Answered by the central Authorizer (`exam_type:*` permissions, scope-aware).
 */
class ExamTypePolicy
{
    use DelegatesToAuthorizer, HandlesAuthorization;

    protected function resourceKey(): string
    {
        return 'exam_type';
    }
}
