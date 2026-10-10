<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mcp:notify-expiring')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('clearance:remind-pending')->dailyAt('08:30')->withoutOverlapping();
Schedule::command('sessions:prune')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('portal:check')->dailyAt((string) config('result_portal.check_time'))->withoutOverlapping();
Schedule::command('portal:recheck-pending')->dailyAt('07:00')->withoutOverlapping();

Schedule::command('notifications:process-outbox')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:send-digests')->dailyAt((string) config('notifications.digest_time'))->withoutOverlapping();

Schedule::command('notifications:scan')->dailyAt('08:15')->withoutOverlapping();

/*
 * Any scheduled command that fails is reported to the super admins.
 */
foreach (Schedule::events() as $scheduled) {
    $scheduled->onFailure(function () use ($scheduled): void {
        app(App\Services\Notifications\NotificationEvents::class)->emit('system.scheduled_task_failed', [
            'command' => trim(str_replace(['\'php\'', '\'artisan\'', PHP_BINARY, '"'], '', (string) $scheduled->command)),
            'time' => now()->format('d M Y H:i'),
        ], 'task_failed:'.md5((string) $scheduled->command).':'.now()->format('YmdH'));
    });
}
