<?php

namespace App\Services\ResultPortal;

use App\Enums\ResultPullStatus;
use App\Events\Portal\StudentGradeChanged;
use App\Events\Portal\StudentResultPulled;
use App\Events\Portal\StudentResultPullFailed;
use App\Exceptions\Domain\ResultPortalException;
use App\Models\ResultPull;
use App\Services\ResultPortal\Contracts\ResultSource;

/**
 * Runs one queued pull and records the outcome on it. Transient portal
 * errors are rethrown so the queue retries them; everything else ends the
 * pull as `failed` with a readable message. A pull for one exam where the
 * portal has no result for the student is `pending` and re-checked later.
 */
class ResultPullRunner
{
    public function __construct(protected ResultSource $source, protected PortalResultImporter $importer) {}

    public function run(ResultPull $pull): void
    {
        $pull->update([
            'status' => ResultPullStatus::Running,
            'attempts' => $pull->attempts + 1,
            'started_at' => now(),
            'message' => null,
            'next_check_at' => null,
        ]);

        try {
            $fetch = $this->source->fetch($pull->student, $pull->portal_exam_id);
            $imported = $this->importer->import($pull->student, $pull, $fetch->rows, $fetch->outcomes);
        } catch (ResultPortalException $exception) {
            if ($exception->transient) {
                $pull->update(['status' => ResultPullStatus::Queued, 'message' => $exception->getMessage()]);

                throw $exception;
            }

            $this->fail($pull, $exception->getMessage());

            return;
        }

        if ($pull->portal_exam_id !== null && $imported['found'] === 0 && $fetch->notVerified > 0) {
            $this->waitForResult($pull, $fetch);

            return;
        }

        $pull->update([
            'status' => ResultPullStatus::Success,
            'exams_checked' => $fetch->examsChecked,
            'results_found' => $imported['found'],
            'results_changed' => $imported['changed'],
            'message' => $imported['found'] === 0
                ? 'The portal has no published result for this student yet.'
                : "{$imported['found']} result(s) read, {$imported['changed']} new or changed.",
            'finished_at' => now(),
        ]);

        if ($imported['changed'] > 0) {
            event(new StudentResultPulled($pull->fresh()));
        }

        if ($imported['grade_changes']->isNotEmpty()) {
            event(new StudentGradeChanged($pull->fresh(), $imported['grade_changes']));
        }
    }

    public function fail(ResultPull $pull, string $message): void
    {
        $pull->update(['status' => ResultPullStatus::Failed, 'message' => $message, 'finished_at' => now()]);

        event(new StudentResultPullFailed($pull->fresh()));
    }

    /**
     * After a confirmed publication a student without a result is re-checked
     * a few times (late individual results), then closed as "not in this exam".
     */
    protected function waitForResult(ResultPull $pull, PortalFetch $fetch): void
    {
        $days = array_values(config('result_portal.pending_recheck_days'));
        $checks = $pull->pending_checks + 1;

        if ($checks <= count($days)) {
            $pull->update([
                'status' => ResultPullStatus::Pending,
                'pending_checks' => $checks,
                'exams_checked' => $fetch->examsChecked,
                'next_check_at' => now()->addDays($days[$checks - 1] - ($days[$checks - 2] ?? 0)),
                'message' => "No result for this student in this exam yet (check {$checks} of ".count($days).').',
            ]);

            return;
        }

        $pull->update([
            'status' => ResultPullStatus::Success,
            'pending_checks' => $checks,
            'exams_checked' => $fetch->examsChecked,
            'results_found' => 0,
            'message' => 'No result for this student in this exam after '.count($days).' re-checks; the student probably did not sit it.',
            'finished_at' => now(),
        ]);
    }
}
