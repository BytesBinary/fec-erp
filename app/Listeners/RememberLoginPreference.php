<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Remembers whether "remember me" was ticked so the session record created
 * on the first authenticated request gets the 30-day instead of the 12-hour
 * inactivity window.
 */
class RememberLoginPreference
{
    public function handle(Login $event): void
    {
        if (app()->bound('session.store') && request()->hasSession()) {
            request()->session()->put('erp.remember', (bool) $event->remember);
        }
    }
}
