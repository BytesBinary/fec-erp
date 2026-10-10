<?php

namespace App\Console\Commands;

use App\Exceptions\Domain\ResultPortalException;
use App\Models\PortalCheckRun;
use App\Models\PortalExam;
use App\Services\ResultPortal\ExamCatalogSync;
use Illuminate\Console\Command;

class PortalSyncCatalog extends Command
{
    protected $signature = 'portal:sync-catalog';

    protected $description = 'Save the portal exam list for CSE, EEE and Civil (first-time sync)';

    public function handle(ExamCatalogSync $sync): int
    {
        if (! config('result_portal.enabled')) {
            $this->components->warn('The result portal sync is switched off.');

            return self::SUCCESS;
        }

        $run = PortalCheckRun::query()->create(['kind' => 'catalog', 'status' => 'running', 'started_at' => now()]);

        try {
            $new = $sync->sync();
        } catch (ResultPortalException $exception) {
            $run->update(['status' => 'failed', 'message' => $exception->getMessage(), 'finished_at' => now()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $total = PortalExam::query()->count();
        $run->update(['status' => 'success', 'exams_total' => $total, 'new_exams' => $new->count(), 'finished_at' => now()]);
        $this->components->info("{$total} exams saved, {$new->count()} new.");

        return self::SUCCESS;
    }
}
