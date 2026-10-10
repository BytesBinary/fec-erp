<?php

namespace App\Console\Commands;

use App\Services\Mcp\IntegrationService;
use Illuminate\Console\Command;

class NotifyExpiringIntegrations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mcp:notify-expiring';

    /**
     * @var string
     */
    protected $description = 'Tell users whose AI integrations expire within 7 days';

    public function handle(IntegrationService $integrations): int
    {
        $count = $integrations->notifyExpiring();

        $this->components->info("Notified about {$count} expiring integration(s).");

        return self::SUCCESS;
    }
}
