<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\PasswordChanged;
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
        if ($user->wasChanged('email')) {
            $this->announceEmailChange($user);
        }

        if (! $user->wasChanged('password')) {
            return;
        }

        $tracker = app(SessionTracker::class);
        $current = Auth::id() === $user->getKey() && request()->hasSession()
            ? $tracker->hash(request()->session()->getId())
            : null;

        $signedOut = $tracker->revokeOthers($user, $current, Auth::user() instanceof User ? Auth::user() : null, 'password_changed');

        $user->notify(new PasswordChanged($signedOut));
    }

    /**
     * Tells both the new and the old address that the email changed (the new
     * one is masked in the text).
     */
    protected function announceEmailChange(User $user): void
    {
        $old = (string) $user->getOriginal('email');
        $new = (string) $user->email;
        $at = strpos($new, '@');
        $masked = $at === false ? '***' : substr($new, 0, 1).'***'.substr($new, $at);

        app(\App\Services\Notifications\NotificationEvents::class)->emit('security.email_changed', ['new_email' => $masked, 'link' => url('/')], 'email_changed:'.$user->getKey().':'.md5($old.'>'.$new), $user, emails: array_filter([$old]));
    }
}
