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
