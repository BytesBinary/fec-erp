<?php

namespace App\Console\Commands;

use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Console\Command;

class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Combine held notifications into one email per recipient';

    public function handle(EmailDeliveryService $deliveries): int
    {
        $this->components->info($deliveries->sendDigests().' digest email(s) queued.');

        return self::SUCCESS;
    }
}
