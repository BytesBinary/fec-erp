<?php

/*
|--------------------------------------------------------------------------
| Account security: sessions, devices and two-factor authentication
|--------------------------------------------------------------------------
|
| Spec §3A. Every value can be overridden per environment.
|
*/

return [

    'sessions' => [
        /*
         | Inactivity expiry in minutes: 12 hours without "remember me",
         | 30 days with it. The framework session lifetime (config/session.php)
         | must be at least the longer of the two.
         */
        'inactivity_minutes' => (int) env('SECURITY_SESSION_MINUTES', 720),
        'remember_minutes' => (int) env('SECURITY_SESSION_REMEMBER_MINUTES', 43200),

        /*
         | `last_active_at` is written at most once per this many seconds.
         */
        'touch_interval_seconds' => 60,

        /*
         | Revoked / expired session records are kept this many days for audit.
         */
        'retention_days' => 90,
    ],

    'two_factor' => [
        'issuer' => env('SECURITY_2FA_ISSUER'),
        'window' => 1,
        'digits' => 6,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        'recovery_code_count' => 10,
        'recovery_warn_below' => 3,
        'trust_device_days' => 30,
        'trust_device_enabled' => true,
        'trust_cookie' => 'erp_trusted_device',
    ],

];
