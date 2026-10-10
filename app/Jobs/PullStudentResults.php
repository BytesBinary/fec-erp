<?php

namespace App\Jobs;

use App\Models\ResultPull;
use App\Services\ResultPortal\ResultPullRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PullStudentResults implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $resultPullId)
    {
        $this->tries = (int) config('result_portal.tries');
    }

    public int $tries;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('result_portal.backoff_seconds');
    }

    public function uniqueId(): string
    {
        return (string) $this->resultPullId;
    }

    public function handle(ResultPullRunner $runner): void
    {
        $pull = ResultPull::query()->with('student.department', 'student.batch')->find($this->resultPullId);

        if ($pull === null || $pull->status->isFinished()) {
            return;
        }

        $runner->run($pull);
    }

    public function failed(Throwable $exception): void
    {
        $pull = ResultPull::query()->find($this->resultPullId);

        if ($pull !== null) {
            app(ResultPullRunner::class)->fail($pull, $exception->getMessage());
        }
    }
}
