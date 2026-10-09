<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExamTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamType');
    }

    public function view(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('View:ExamType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamType');
    }

    public function update(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('Update:ExamType');
    }

    public function delete(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('Delete:ExamType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamType');
    }

    public function restore(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('Restore:ExamType');
    }

    public function forceDelete(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('ForceDelete:ExamType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamType');
    }

    public function replicate(AuthUser $authUser, ExamType $examType): bool
    {
        return $authUser->can('Replicate:ExamType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamType');
    }
}
