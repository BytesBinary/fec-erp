<?php

return [

    /*
     | Master switch of the email pipeline. In-app (bell) notifications are
     | always on; this only controls whether events enter the outbox.
     */
    'enabled' => (bool) env('NOTIFICATIONS_ENABLED', true),

    /*
     | `log` writes emails to the application log (development, default);
     | `mail` sends them through the configured Laravel mailer (set MAIL_*).
     */
    'driver' => env('NOTIFICATION_DRIVER', 'log'),

    'queue' => env('NOTIFICATION_QUEUE', 'default'),

    'outbox_batch' => 100,

    'outbox_max_attempts' => 5,

    'delivery_tries' => 5,

    'delivery_backoff_seconds' => [60, 300, 900, 3600],

    /*
     | "Emails are failing" fires when this many deliveries failed within the
     | last hour, at most once every six hours.
     */
    'failure_alert_threshold' => 3,

    'denied_spike_threshold' => 100,

    'digest_time' => env('NOTIFICATION_DIGEST_TIME', '07:30'),

    'digest_max_items' => 50,

];
