<?php

namespace App\Console\Commands;

use App\Services\ResultPortal\PublicationDetector;
use Illuminate\Console\Command;

class PortalCheck extends Command
{
    protected $signature = 'portal:check';

    protected $description = 'Daily check: detect newly published exam results and confirm them';

    public function handle(PublicationDetector $detector): int
    {
        if (! config('result_portal.enabled')) {
            $this->components->warn('The result portal sync is switched off.');

            return self::SUCCESS;
        }

        $report = $detector->run();

        $this->components->info("{$report['new_exams']} new exam(s), {$report['confirmed']} publication(s) confirmed.");

        if ($report['failed']) {
            $this->components->error((string) $report['message']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
