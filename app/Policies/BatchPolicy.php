<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\User;

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function view(User $user, Batch $batch): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Batch $batch): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Batch $batch): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Batch $batch): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Batch $batch): bool
    {
        return $user->isAdmin();
    }
}
