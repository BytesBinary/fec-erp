<?php

namespace App\Services\ResultPortal;

use App\Events\Portal\PortalHealthFailed;
use App\Events\Portal\PublicationConfirmed;
use App\Events\Portal\PublicationDetected;
use App\Exceptions\Domain\ResultPortalException;
use App\Models\PortalCheckRun;
use App\Models\PortalExam;
use App\Models\PortalProbe;
use App\Models\PortalPublication;
use App\Models\Student;

/**
 * The daily check. A new exam id on the portal's list is a publication
 * candidate; probe students then prove that results are really visible. A
 * portal error or an unknown page is never taken as "not published".
 */
class PublicationDetector
{
    public function __construct(
        protected ExamCatalogSync $sync,
        protected ProbeSelector $probes,
        protected EligibleStudents $eligible,
        protected DuPortalResultSource $source,
        protected ResultPullService $pulls,
    ) {}

    /**
     * @return array{new_exams: int, confirmed: int, failed: bool, message: ?string}
     */
    public function run(string $kind = 'daily'): array
    {
        $run = PortalCheckRun::query()->create(['kind' => $kind, 'status' => 'running', 'started_at' => now()]);

        try {
            $newExams = $this->sync->sync();

            foreach ($newExams as $exam) {
                $publication = PortalPublication::query()->create([
                    'portal_exam_id' => $exam->portal_exam_id,
                    'program_id' => $exam->program_id,
                    'status' => 'detected',
                    'mode' => config('result_portal.shadow_mode') ? 'shadow' : 'live',
                    'detected_at' => now(),
                    'next_check_at' => now(),
                ]);

                $exam->update(['status' => 'detected']);
                event(new PublicationDetected($publication));
            }

            $confirmed = 0;

            PortalPublication::query()
                ->whereIn('status', ['detected', 'awaiting'])
                ->where(fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->each(function (PortalPublication $publication) use (&$confirmed): void {
                    if ($this->confirm($publication)) {
                        $confirmed++;
                    }
                });

            $run->update(['status' => 'success', 'exams_total' => PortalExam::query()->count(), 'new_exams' => $newExams->count(), 'publications_confirmed' => $confirmed, 'finished_at' => now()]);

            return ['new_exams' => $newExams->count(), 'confirmed' => $confirmed, 'failed' => false, 'message' => null];
        } catch (ResultPortalException $exception) {
            $run->update(['status' => 'failed', 'message' => $exception->getMessage(), 'finished_at' => now()]);
            event(new PortalHealthFailed($exception->getMessage()));

            return ['new_exams' => 0, 'confirmed' => 0, 'failed' => true, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Probes one publication candidate. True when results were seen.
     */
    public function confirm(PortalPublication $publication): bool
    {
        $exam = PortalExam::query()->where('portal_exam_id', $publication->portal_exam_id)->firstOrFail();
        $students = $this->probes->select($exam, (int) config('result_portal.probes_per_exam'));

        $publication->update(['last_checked_at' => now(), 'check_count' => $publication->check_count + 1]);
        $exam->update(['last_checked_at' => now(), 'check_count' => $exam->check_count + 1]);

        if ($students->isEmpty()) {
            $publication->update(['status' => 'awaiting', 'notes' => 'No student with a registration number could be used as a probe yet.', 'next_check_at' => $this->nextCheck($publication)]);

            return false;
        }

        $found = false;
        $unreadable = false;
        $delayMicroseconds = (int) config('result_portal.request_delay_ms') * 1000;

        foreach ($students->values() as $index => $student) {
            if ($index > 0 && $delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }

            [$outcome, $message, $page] = $this->probe($student, $exam);

            PortalProbe::query()->create(['portal_exam_id' => $exam->portal_exam_id, 'student_id' => $student->id, 'outcome' => $outcome, 'message' => $message, 'checked_at' => now()]);
            $unreadable = $unreadable || in_array($outcome, ['unrecognised', 'error'], true);

            if ($outcome === 'found') {
                $found = true;
                $exam->update(['published_on' => $this->date($page?->meta['Result Publication Date'] ?? null)]);

                break;
            }
        }

        if (! $found) {
            $publication->update(['status' => 'awaiting', 'next_check_at' => $this->nextCheck($publication), 'notes' => $unreadable ? 'A probe lookup failed or could not be read; this is not proof that nothing is published.' : null]);

            if ($unreadable) {
                event(new PortalHealthFailed('A probe lookup on the portal failed or returned a page the parser does not recognise.'));
            }

            return false;
        }

        $exam->update(['status' => 'published', 'confirmed_at' => now()]);
        $publication->update(['status' => $publication->mode === 'shadow' ? 'shadow' : 'confirmed', 'confirmed_at' => now(), 'next_check_at' => null, 'notes' => null]);
        event(new PublicationConfirmed($publication->fresh()));

        if ($publication->mode === 'live') {
            $this->queueStudents($publication);
        }

        return true;
    }

    /**
     * Admin presses "Run now" on a shadow (or confirmed) publication: queue a
     * pull of that exam for every eligible student.
     */
    public function queueStudents(PortalPublication $publication): int
    {
        $exam = PortalExam::query()->where('portal_exam_id', $publication->portal_exam_id)->firstOrFail();
        $students = $this->eligible->forExam($exam);

        $publication->update(['mode' => 'live', 'status' => 'confirmed', 'students_total' => $students->count()]);

        $students->each(fn (Student $student) => $this->pulls->queueFor($student, 'publication', null, $exam->portal_exam_id, $publication->id));

        return $students->count();
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?PortalPage}
     */
    protected function probe(Student $student, PortalExam $exam): array
    {
        try {
            $page = $this->source->probe($student, $exam->portal_exam_id);
        } catch (ResultPortalException $exception) {
            $unrecognised = str_contains($exception->getMessage(), 'not recognised');

            return [$unrecognised ? 'unrecognised' : 'error', $exception->getMessage(), null];
        }

        return [$page->status === PortalPageStatus::Found ? 'found' : 'not_verified', null, $page];
    }

    protected function nextCheck(PortalPublication $publication): \Illuminate\Support\Carbon
    {
        $dailyUntil = $publication->detected_at->copy()->addDays((int) config('result_portal.awaiting_daily_days'));

        return now()->lessThan($dailyUntil) ? now()->addDay() : now()->addWeek();
    }

    protected function date(?string $value): ?string
    {
        return $value !== null && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1 ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    }
}
