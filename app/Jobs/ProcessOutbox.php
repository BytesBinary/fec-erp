<?php

namespace App\Jobs;

use App\Services\Notifications\OutboxProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drains the notification outbox. Unique, so many events in a burst start one run.
 */
class ProcessOutbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 30;

    public function handle(OutboxProcessor $processor): void
    {
        $processor->process();
    }
}
