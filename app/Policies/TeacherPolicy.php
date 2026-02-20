<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Teacher $teacher): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $user->teacher?->id === $teacher->id;
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }
}
