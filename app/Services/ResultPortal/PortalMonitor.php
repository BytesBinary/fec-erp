<?php

namespace App\Services\ResultPortal;

use App\Exceptions\Domain\ResultPortalException;
use App\Exceptions\Domain\ValidationException;
use App\Models\PortalCheckRun;
use App\Models\PortalExam;
use App\Models\PortalProbe;
use App\Models\PortalPublication;
use App\Models\ResultPull;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Carbon;

/**
 * What the "Portal monitor" screen and the MCP tools show and do: the saved
 * exam list, the daily check, confirmed publications and the pulls they started.
 */
class PortalMonitor
{
    public function __construct(
        protected Authorizer $authorizer,
        protected ExamCatalogSync $sync,
        protected PublicationDetector $detector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(User $actor): array
    {
        $this->authorizer->authorize($actor, 'portal_monitor:view');

        $programs = collect(config('result_portal.department_programs'))->groupBy(fn (int $program): int => $program, preserveKeys: true)->map(fn ($codes) => $codes->keys()->implode('/'));

        return [
            'enabled' => (bool) config('result_portal.enabled'),
            'shadow_mode' => (bool) config('result_portal.shadow_mode'),
            'last_success' => PortalCheckRun::query()->where('status', 'success')->latest('id')->first(),
            'last_failure' => PortalCheckRun::query()->where('status', 'failed')->latest('id')->first(),
            'next_check_at' => $this->nextCheck(),
            'exams_by_program' => PortalExam::query()->get()->groupBy('program_id')->map(fn ($exams, int $program): array => [
                'label' => $programs[$program] ?? "Program {$program}",
                'total' => $exams->count(),
                'by_year' => $exams->groupBy(fn (PortalExam $exam): string => (string) ($exam->exam_year ?? 'Unknown'))->map->count()->sortKeysDesc()->all(),
            ])->all(),
            'publications' => PortalPublication::query()->latest('id')->limit(20)->get(),
            'publication_counts' => PortalPublication::query()->get()->countBy('status')->all(),
            'pull_counts' => ResultPull::query()->get()->countBy(fn (ResultPull $pull): string => $pull->status->value)->all(),
            'probes' => PortalProbe::query()->with('student.user')->latest('id')->limit(15)->get(),
            'runs' => PortalCheckRun::query()->latest('id')->limit(8)->get(),
        ];
    }

    /**
     * First-time (or manual) sync of the exam list.
     *
     * @return array{total: int, new: int}
     */
    public function syncCatalog(User $actor): array
    {
        $this->authorizer->authorize($actor, 'portal_monitor:manage');

        $run = PortalCheckRun::query()->create(['kind' => 'catalog', 'status' => 'running', 'started_at' => now()]);

        try {
            $new = $this->sync->sync();
        } catch (ResultPortalException $exception) {
            $run->update(['status' => 'failed', 'message' => $exception->getMessage(), 'finished_at' => now()]);

            throw new ValidationException($exception->getMessage());
        }

        $total = PortalExam::query()->count();
        $run->update(['status' => 'success', 'exams_total' => $total, 'new_exams' => $new->count(), 'finished_at' => now()]);

        return ['total' => $total, 'new' => $new->count()];
    }

    /**
     * Runs the daily check now.
     *
     * @return array{new_exams: int, confirmed: int, failed: bool, message: ?string}
     */
    public function checkNow(User $actor): array
    {
        $this->authorizer->authorize($actor, 'portal_monitor:manage');

        return $this->detector->run('manual');
    }

    /**
     * "Run now" on a shadow or confirmed publication: pull every eligible student.
     */
    public function runPublication(User $actor, PortalPublication $publication): int
    {
        $this->authorizer->authorize($actor, 'portal_monitor:manage');

        if (! in_array($publication->status, ['shadow', 'confirmed'], true)) {
            throw new ValidationException('Only a confirmed publication can be run. This one is "'.$publication->status.'".');
        }

        return $this->detector->queueStudents($publication);
    }

    protected function nextCheck(): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('result_portal.check_time')) + [0, 0]);
        $next = now()->setTime($hour, $minute);

        return $next->isPast() ? $next->addDay() : $next;
    }
}
