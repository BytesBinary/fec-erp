<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamDuty;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExamDutyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamDuty');
    }

    public function view(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('View:ExamDuty');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamDuty');
    }

    public function update(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('Update:ExamDuty');
    }

    public function delete(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('Delete:ExamDuty');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamDuty');
    }

    public function restore(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('Restore:ExamDuty');
    }

    public function forceDelete(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('ForceDelete:ExamDuty');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamDuty');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamDuty');
    }

    public function replicate(AuthUser $authUser, ExamDuty $examDuty): bool
    {
        return $authUser->can('Replicate:ExamDuty');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamDuty');
    }
}
