<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Security\SessionTracker;
use Illuminate\Support\Facades\Auth;

/**
 * A password change signs the account out of every other device (spec §3A.1).
 * The session making the change (if it is the account's own) stays signed in.
 */
class UserPasswordObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged('password')) {
            return;
        }

        $tracker = app(SessionTracker::class);
        $current = Auth::id() === $user->getKey() && request()->hasSession()
            ? $tracker->hash(request()->session()->getId())
            : null;

        $tracker->revokeOthers($user, $current, Auth::user() instanceof User ? Auth::user() : null, 'password_changed');
    }
}
