<?php

namespace App\Console\Commands;

use App\Services\Notifications\OutboxProcessor;
use Illuminate\Console\Command;

class ProcessNotificationOutbox extends Command
{
    protected $signature = 'notifications:process-outbox';

    protected $description = 'Turn pending notification events into email deliveries (also recovers events left by a crash)';

    public function handle(OutboxProcessor $processor): int
    {
        $this->components->info($processor->process().' event(s) processed.');

        return self::SUCCESS;
    }
}
