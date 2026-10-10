<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationRules;
use Illuminate\Console\Command;

class SyncNotificationRules extends Command
{
    protected $signature = 'notifications:sync-rules';

    protected $description = 'Create the missing email event rules from config/notification_events.php (never overwrites edits)';

    public function handle(NotificationRules $rules): int
    {
        $this->components->info($rules->sync().' rule(s) added.');

        return self::SUCCESS;
    }
}
