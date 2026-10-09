<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Batch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Batch');
    }

    public function view(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('View:Batch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Batch');
    }

    public function update(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('Update:Batch');
    }

    public function delete(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('Delete:Batch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Batch');
    }

    public function restore(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('Restore:Batch');
    }

    public function forceDelete(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('ForceDelete:Batch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Batch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Batch');
    }

    public function replicate(AuthUser $authUser, Batch $batch): bool
    {
        return $authUser->can('Replicate:Batch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Batch');
    }
}
