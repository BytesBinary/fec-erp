<?php

namespace App\Services\ResultPortal;

use App\Enums\ResultPullStatus;
use App\Jobs\PullStudentResults;
use App\Models\PortalResult;
use App\Models\ResultPull;
use App\Models\Student;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queues and tracks pulls of official results. Called by the student observer
 * (panel and MCP both create students through the model), by the "Student
 * results" screen and by the MCP tools.
 */
class ResultPullService
{
    public function __construct(protected Authorizer $authorizer) {}

    /**
     * Records and (when possible) queues a pull. Does nothing when the sync
     * is switched off. A pull that cannot start is recorded as `skipped` with
     * the reason so staff see it.
     */
    public function queueFor(Student $student, string $trigger, ?User $requestedBy = null, ?int $portalExamId = null, ?int $publicationId = null): ?ResultPull
    {
        if (! config('result_portal.enabled')) {
            return null;
        }

        $student->loadMissing('department');

        $reason = match (true) {
            blank($student->registration_number) => 'The student has no registration number.',
            ! ctype_digit((string) $student->registration_number) => 'The registration number is not numeric; the portal only accepts digits.',
            ! isset(config('result_portal.department_programs')[$student->department?->code]) => 'The student\'s department is not mapped to a portal program.',
            default => null,
        };

        $pull = ResultPull::query()->create([
            'student_id' => $student->getKey(),
            'requested_by' => $requestedBy?->getKey(),
            'trigger' => $trigger,
            'portal_exam_id' => $portalExamId,
            'portal_publication_id' => $publicationId,
            'status' => $reason === null ? ResultPullStatus::Queued : ResultPullStatus::Skipped,
            'message' => $reason,
            'queued_at' => now(),
            'finished_at' => $reason === null ? null : now(),
        ]);

        if ($reason === null) {
            PullStudentResults::dispatch($pull->getKey())->onQueue((string) config('result_portal.queue'))->afterCommit();
        }

        return $pull;
    }

    /**
     * Pulls the actor may see, newest first.
     *
     * @return Builder<ResultPull>
     */
    public function query(User $actor): Builder
    {
        $this->authorizer->authorize($actor, 'result_pull:list');

        return $this->authorizer->scopeFor($actor, 'result_pull:list')->constrain(
            ResultPull::query()->with('student.user', 'student.department')->latest('id'),
            ['department' => fn (Builder $q, array $ids) => $q->whereHas('student', fn (Builder $s) => $s->whereIn('department_id', $ids))],
        );
    }

    /**
     * @return array<string, int>
     */
    public function counts(User $actor): array
    {
        $counts = $this->latestPerStudent($this->query($actor))->get()->countBy(fn (ResultPull $pull): string => $pull->status->value);

        return collect(ResultPullStatus::cases())->mapWithKeys(fn (ResultPullStatus $status): array => [$status->value => (int) $counts->get($status->value, 0)])->all();
    }

    public function retry(User $actor, ResultPull $pull): ResultPull
    {
        $this->authorizer->authorize($actor, 'result_pull:retry', $pull);

        return $this->queueFor($pull->student, 'retry', $actor) ?? $pull;
    }

    /**
     * Retries the latest failed or skipped pull of every student in scope.
     */
    public function retryAllFailed(User $actor): int
    {
        $this->authorizer->authorize($actor, 'result_pull:retry');

        $pulls = $this->latestPerStudent($this->query($actor))->get()
            ->filter(fn (ResultPull $pull): bool => in_array($pull->status, [ResultPullStatus::Failed, ResultPullStatus::Skipped], true));

        $pulls->each(fn (ResultPull $pull) => $this->queueFor($pull->student, 'retry', $actor));

        return $pulls->count();
    }

    /**
     * The current portal grades of a student, with what each replaced.
     *
     * @return Collection<int, PortalResult>
     */
    public function currentResults(User $actor, Student $student): Collection
    {
        $this->authorizer->authorize($actor, 'result:view', $student);

        return PortalResult::query()->where('student_id', $student->getKey())->current()->orderBy('course_code')->get();
    }

    /**
     * @param  Builder<ResultPull>  $query
     * @return Builder<ResultPull>
     */
    protected function latestPerStudent(Builder $query): Builder
    {
        return $query->whereIn('id', ResultPull::query()->selectRaw('max(id)')->groupBy('student_id'));
    }
}
