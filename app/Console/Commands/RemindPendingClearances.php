<?php

namespace App\Console\Commands;

use App\Services\Clearance\ClearanceReminderService;
use Illuminate\Console\Command;

class RemindPendingClearances extends Command
{
    /**
     * @var string
     */
    protected $signature = 'clearance:remind-pending';

    /**
     * @var string
     */
    protected $description = 'Remind approvers of waiting clearances, escalate old ones and send the office digest';

    public function handle(ClearanceReminderService $reminders): int
    {
        $result = $reminders->run();

        $this->components->info("Reminded {$result['reminded']} request(s), escalated {$result['escalated']}, digest sent to {$result['digest']} office user(s).");

        return self::SUCCESS;
    }
}
