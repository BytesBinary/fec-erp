<?php

namespace App\Console\Commands;

use App\Enums\ResultPullStatus;
use App\Jobs\PullStudentResults;
use App\Models\ResultPull;
use Illuminate\Console\Command;

class PortalRecheckPending extends Command
{
    protected $signature = 'portal:recheck-pending';

    protected $description = 'Re-check students who had no result yet after a confirmed publication';

    public function handle(): int
    {
        if (! config('result_portal.enabled')) {
            return self::SUCCESS;
        }

        $count = 0;

        ResultPull::query()
            ->where('status', ResultPullStatus::Pending->value)
            ->where('next_check_at', '<=', now())
            ->each(function (ResultPull $pull) use (&$count): void {
                $pull->update(['status' => ResultPullStatus::Queued, 'next_check_at' => null]);
                PullStudentResults::dispatch($pull->getKey())->onQueue((string) config('result_portal.queue'));
                $count++;
            });

        $this->components->info("{$count} pending pull(s) queued again.");

        return self::SUCCESS;
    }
}
