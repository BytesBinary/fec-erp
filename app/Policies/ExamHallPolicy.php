<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamHall;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExamHallPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamHall');
    }

    public function view(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('View:ExamHall');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamHall');
    }

    public function update(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('Update:ExamHall');
    }

    public function delete(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('Delete:ExamHall');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamHall');
    }

    public function restore(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('Restore:ExamHall');
    }

    public function forceDelete(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('ForceDelete:ExamHall');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamHall');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamHall');
    }

    public function replicate(AuthUser $authUser, ExamHall $examHall): bool
    {
        return $authUser->can('Replicate:ExamHall');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamHall');
    }
}
