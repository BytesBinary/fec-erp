<?php

namespace App\Console\Commands;

use App\Services\Security\SessionTracker;
use Illuminate\Console\Command;

class PruneSessions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sessions:prune';

    /**
     * @var string
     */
    protected $description = 'Delete revoked/expired login session records older than the retention period (90 days)';

    public function handle(SessionTracker $sessions): int
    {
        $this->components->info('Pruned '.$sessions->prune().' session record(s).');

        return self::SUCCESS;
    }
}
