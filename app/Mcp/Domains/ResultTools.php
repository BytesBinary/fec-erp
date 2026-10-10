<?php

namespace App\Mcp\Domains;

use App\Exceptions\Domain\NotFoundException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\PortalPublication;
use App\Models\ResultPull;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\ResultPortal\PortalMonitor;
use App\Services\ResultPortal\ResultPullService;
use App\Services\Results\GradingScaleService;
use App\Services\Results\ResultService;

/**
 * Results workflow and the student's published views. Students only ever see
 * published results, for themselves.
 */
final class ResultTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $studentParam = fn (): Param => Param::integer('student_id', 'Student id. Students omit it (they can only see themselves).');
        $resolve = function (User $actor, array $args): Student {
            if (isset($args['student_id'])) {
                return Student::query()->findOrFail($args['student_id']);
            }

            return app(ProfileService::class)->studentOf($actor) ?? throw new NotFoundException('You are not a student; pass student_id.');
        };

        return [
            ToolDefinition::make('result_get_semester', 'Semester result', 'Get published results of one semester (or all) for a student: each course with grade and grade point, the semester GPA and the CGPA. Unpublished results are never shown.')
                ->permission('result:view')
                ->params($studentParam(), Param::integer('semester_id', 'Only this semester; omit for all published semesters.'))
                ->covers(ResultService::class.'::transcript')
                ->handler(function (User $actor, array $args) use ($resolve): array {
                    $transcript = app(ResultService::class)->transcript($actor, $resolve($actor, $args));

                    if (isset($args['semester_id'])) {
                        $transcript['semesters'] = array_values(array_filter($transcript['semesters'], fn (array $semester): bool => $semester['semester_id'] === (int) $args['semester_id']));
                    }

                    return ['summary' => count($transcript['semesters']).' semester(s); CGPA '.$transcript['cgpa_display'].'.', 'data' => $transcript];
                }),

            ToolDefinition::make('result_get_cgpa', 'CGPA', 'Get a student\'s CGPA (rounded half up to 2 decimals), counted and earned credits. Based on published results only; the best attempt of a repeated course counts.')
                ->permission('result:view')
                ->params($studentParam())
                ->covers(ResultService::class.'::cgpa')
                ->handler(function (User $actor, array $args) use ($resolve): array {
                    $summary = app(ResultService::class)->cgpa($actor, $resolve($actor, $args));

                    return ['summary' => 'CGPA '.$summary->display().'.', 'data' => ['cgpa' => $summary->gpa, 'cgpa_display' => $summary->display(), 'counted_credits' => $summary->countedCredits, 'earned_credits' => $summary->earnedCredits]];
                }),

            ToolDefinition::make('result_pull_list', 'Result pulls', 'List the official-result pulls from the exam portal (newest first) with status success / failed / waiting / skipped and the reason for failures, plus the latest status counts. Use it to find which students\' results could not be pulled.')
                ->permission('result_pull:list')
                ->params(Param::enum('status', 'Only this status.', ['queued', 'running', 'success', 'failed', 'skipped']), Param::integer('limit', 'Rows to return (default 50, max 200).'))
                ->covers(ResultPullService::class.'::query', ResultPullService::class.'::counts')
                ->handler(function (User $actor, array $args): array {
                    $service = app(ResultPullService::class);
                    $pulls = $service->query($actor)
                        ->when(isset($args['status']), fn ($query) => $query->where('status', $args['status']))
                        ->limit(min((int) ($args['limit'] ?? 50), 200))
                        ->get()
                        ->map(fn (ResultPull $pull): array => [
                            'id' => $pull->id,
                            'student_id' => $pull->student_id,
                            'student' => $pull->student->user->name,
                            'registration_number' => $pull->student->registration_number,
                            'status' => $pull->status->value,
                            'results_found' => $pull->results_found,
                            'results_changed' => $pull->results_changed,
                            'message' => $pull->message,
                            'finished_at' => $pull->finished_at?->toIso8601String(),
                        ])->all();

                    return ['summary' => count($pulls).' pull(s).', 'data' => ['counts' => $service->counts($actor), 'pulls' => $pulls]];
                }),

            ToolDefinition::make('result_pull_retry', 'Retry a result pull', 'Queue a new pull for the student of a failed or skipped result pull.')
                ->permission('result_pull:retry')
                ->params(Param::integer('pull_id', 'Result pull id (from result_pull_list).', required: true))
                ->write()
                ->covers(ResultPullService::class.'::retry')
                ->handler(function (User $actor, array $args): array {
                    $pull = ResultPull::query()->find($args['pull_id']) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'result pull']));
                    $new = app(ResultPullService::class)->retry($actor, $pull);

                    return ['summary' => "Pull {$new->id} is {$new->status->value}.", 'data' => ['id' => $new->id, 'status' => $new->status->value], 'entity' => ['ResultPull', $new->id]];
                }),

            ToolDefinition::make('result_pull_retry_failed', 'Retry all failed pulls', 'Queue a new pull for every student whose latest pull failed or was skipped (within your scope).')
                ->permission('result_pull:retry')
                ->write()
                ->covers(ResultPullService::class.'::retryAllFailed')
                ->handler(function (User $actor): array {
                    $count = app(ResultPullService::class)->retryAllFailed($actor);

                    return ['summary' => "{$count} pull(s) queued again.", 'data' => ['queued' => $count]];
                }),

            ToolDefinition::make('result_portal_get', 'Official portal results', 'Get a student\'s current course grades pulled from the exam portal. A grade that replaced an earlier attempt carries change_type improved / retake / declined and the previous grade.')
                ->permission('result:view')
                ->params($studentParam())
                ->covers(ResultPullService::class.'::currentResults')
                ->handler(function (User $actor, array $args) use ($resolve): array {
                    $rows = app(ResultPullService::class)->currentResults($actor, $resolve($actor, $args))->map(fn ($row): array => [
                        'course_code' => $row->course_code,
                        'course_title' => $row->course_title,
                        'letter' => $row->letter,
                        'grade_point' => $row->grade_point,
                        'exam' => $row->exam_title,
                        'change_type' => $row->change_type?->value,
                        'previous_letter' => $row->previous_letter,
                    ])->all();

                    return ['summary' => count($rows).' course result(s) from the portal.', 'data' => ['results' => $rows]];
                }),

            ToolDefinition::make('portal_monitor_get', 'Result portal monitor', 'Status of the university result-portal sync: last and next daily check, exams saved per department and year, detected publications (detected / awaiting / shadow / confirmed), student pull counts, recent probes and runs.')
                ->permission('portal_monitor:view')
                ->covers(PortalMonitor::class.'::status')
                ->handler(function (User $actor): array {
                    $status = app(PortalMonitor::class)->status($actor);

                    return ['summary' => 'Portal sync '.($status['enabled'] ? 'on' : 'off').($status['shadow_mode'] ? ' (shadow mode)' : '').'; '.count($status['publications']).' recent publication(s).', 'data' => [
                        'enabled' => $status['enabled'],
                        'shadow_mode' => $status['shadow_mode'],
                        'last_success_at' => $status['last_success']?->finished_at?->toIso8601String(),
                        'last_failure' => $status['last_failure']?->message,
                        'next_check_at' => $status['next_check_at']->toIso8601String(),
                        'exams_by_program' => $status['exams_by_program'],
                        'publication_counts' => $status['publication_counts'],
                        'pull_counts' => $status['pull_counts'],
                        'publications' => $status['publications']->map(fn (PortalPublication $publication): array => [
                            'id' => $publication->id, 'portal_exam_id' => $publication->portal_exam_id, 'status' => $publication->status, 'mode' => $publication->mode,
                            'detected_at' => $publication->detected_at->toIso8601String(), 'students_total' => $publication->students_total,
                        ])->all(),
                    ]];
                }),

            ToolDefinition::make('portal_catalog_sync', 'Sync the portal exam list', 'First-time (or manual) sync: fetch the exam lists of CSE, EEE and Civil from the portal and save them. Old exams are stored as known, not as new publications.')
                ->permission('portal_monitor:manage')
                ->write()
                ->covers(PortalMonitor::class.'::syncCatalog')
                ->handler(function (User $actor): array {
                    $report = app(PortalMonitor::class)->syncCatalog($actor);

                    return ['summary' => "{$report['total']} exams saved ({$report['new']} new).", 'data' => $report];
                }),

            ToolDefinition::make('portal_check_run', 'Check for new results', 'Run the daily publication check now: read the exam list, detect exams that are new, and confirm their results with probe students. In shadow mode nothing is pulled until portal_publication_run.')
                ->permission('portal_monitor:manage')
                ->write()
                ->covers(PortalMonitor::class.'::checkNow')
                ->handler(function (User $actor): array {
                    $report = app(PortalMonitor::class)->checkNow($actor);

                    return ['summary' => "{$report['new_exams']} new exam(s), {$report['confirmed']} publication(s) confirmed.", 'data' => $report];
                }),

            ToolDefinition::make('portal_publication_run', 'Run a confirmed publication', 'Queue a result pull for every eligible student of a confirmed (or shadow) publication.')
                ->permission('portal_monitor:manage')
                ->params(Param::integer('publication_id', 'Publication id (from portal_monitor_get).', required: true))
                ->write()
                ->covers(PortalMonitor::class.'::runPublication')
                ->handler(function (User $actor, array $args): array {
                    $publication = PortalPublication::query()->find($args['publication_id']) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'publication']));
                    $count = app(PortalMonitor::class)->runPublication($actor, $publication);

                    return ['summary' => "{$count} student pull(s) queued.", 'data' => ['queued' => $count]];
                }),

            ToolDefinition::make('transcript_get', 'Transcript', 'Get the full transcript (all published semesters, GPAs, CGPA, credits).')
                ->permission('transcript:view')
                ->params($studentParam())
                ->covers(ResultService::class.'::transcript')
                ->handler(function (User $actor, array $args) use ($resolve): array {
                    $transcript = app(ResultService::class)->transcript($actor, $resolve($actor, $args));

                    return ['summary' => 'Transcript with CGPA '.$transcript['cgpa_display'].'.', 'data' => $transcript];
                }),

            ToolDefinition::make('result_roster', 'Result roster', 'List the students of a course offering with their marks and result status (draft, submitted, approved, published). For teachers, heads and publishers.')
                ->permission('result:enter_marks|result:approve|result:publish')
                ->params(Param::integer('course_offering_id', 'The offering id.', required: true))
                ->covers(ResultService::class.'::rosterOf')
                ->handler(function (User $actor, array $args): array {
                    $rows = app(ResultService::class)->rosterOf($actor, CourseOffering::query()->findOrFail($args['course_offering_id']));

                    return ['summary' => $rows->count().' student(s).', 'data' => $rows->map(fn (Enrollment $enrollment): array => [
                        'enrollment_id' => $enrollment->id, 'student' => $enrollment->student->user->name, 'roll' => $enrollment->student->roll_number,
                        'marks' => $enrollment->result?->marks, 'letter' => $enrollment->result?->letter, 'status' => $enrollment->result?->status->value,
                    ])->all()];
                }),

            ToolDefinition::make('result_enter_marks', 'Enter marks', 'Enter or change marks (0-100) for one enrollment while its result is still a draft. The grade is derived from the grading scale. Teachers can only do this for their own courses.')
                ->permission('result:enter_marks')
                ->params(Param::integer('enrollment_id', 'The enrollment id (from result_roster).', required: true), Param::number('marks', 'Marks from 0 to 100.', true))
                ->write(idempotent: true)
                ->covers(ResultService::class.'::enterMarks')
                ->handler(function (User $actor, array $args): array {
                    $result = app(ResultService::class)->enterMarks($actor, Enrollment::query()->findOrFail($args['enrollment_id']), (float) $args['marks']);

                    return ['summary' => "Saved marks {$result->marks} (grade {$result->letter}).", 'data' => ToolSupport::item($result), 'entity' => ['Result', $result->id]];
                }),

            ToolDefinition::make('result_submit', 'Submit results', 'Submit all draft results of an offering to the department head. Every enrolled student needs marks first.')
                ->permission('result:submit')
                ->params(Param::integer('course_offering_id', 'The offering id.', required: true))
                ->write(idempotent: true)
                ->covers(ResultService::class.'::submitOffering')
                ->handler(function (User $actor, array $args): array {
                    $count = app(ResultService::class)->submitOffering($actor, CourseOffering::query()->findOrFail($args['course_offering_id']));

                    return ['summary' => "Submitted {$count} result(s).", 'data' => ['count' => $count], 'entity' => ['CourseOffering', $args['course_offering_id']]];
                }),

            ToolDefinition::make('result_approve', 'Approve results', 'Department head approves the submitted results of an offering. Needs confirm=true; without it you get a preview.')
                ->permission('result:approve')
                ->params(Param::integer('course_offering_id', 'The offering id.', required: true))
                ->destructive(function (User $actor, array $args): array {
                    $rows = app(ResultService::class)->rosterOf($actor, CourseOffering::query()->findOrFail($args['course_offering_id']));

                    return ['students' => $rows->count(), 'submitted' => $rows->filter(fn (Enrollment $e): bool => $e->result?->status->value === 'submitted')->count()];
                })
                ->covers(ResultService::class.'::approveOffering')
                ->handler(function (User $actor, array $args): array {
                    $count = app(ResultService::class)->approveOffering($actor, CourseOffering::query()->findOrFail($args['course_offering_id']));

                    return ['summary' => "Approved {$count} result(s).", 'data' => ['count' => $count], 'entity' => ['CourseOffering', $args['course_offering_id']]];
                }),

            ToolDefinition::make('result_publish', 'Publish semester results', 'Publish every approved result of a semester so students can see them. Cannot be undone. Needs confirm=true; the preview says how many results and students are affected.')
                ->permission('result:publish')
                ->params(Param::integer('semester_id', 'The semester id.', required: true))
                ->destructive(fn (User $actor, array $args): array => app(ResultService::class)->previewPublish($actor, Semester::query()->findOrFail($args['semester_id'])))
                ->covers(ResultService::class.'::previewPublish', ResultService::class.'::publishSemester')
                ->handler(function (User $actor, array $args): array {
                    $count = app(ResultService::class)->publishSemester($actor, Semester::query()->findOrFail($args['semester_id']));

                    return ['summary' => "Published {$count} result(s).", 'data' => ['published' => $count], 'entity' => ['Semester', $args['semester_id']]];
                }),

            ToolDefinition::make('grading_scale_get', 'Grading scale', 'Get the grading scale: marks ranges, letters and grade points.')
                ->permission('result:view')
                ->covers(GradingScaleService::class.'::scale')
                ->handler(fn (User $actor): array => ['summary' => 'Grading scale.', 'data' => app(GradingScaleService::class)->scale($actor)->map(fn ($band): array => ['min_mark' => $band->min_mark, 'max_mark' => $band->max_mark, 'letter' => $band->letter, 'grade_point' => $band->grade_point])->values()->all()]),

            ToolDefinition::make('grading_scale_replace', 'Replace grading scale', 'Replace the whole grading scale. Needs confirm=true. Future marks entry uses the new scale; published grades are not recomputed.')
                ->permission('grading_scale:manage')
                ->params(Param::array('bands', 'List of {min_mark, max_mark, letter, grade_point}.', 'object', true))
                ->destructive(fn (User $actor, array $args): array => ['new_band_count' => count($args['bands'])])
                ->covers(GradingScaleService::class.'::replace')
                ->handler(function (User $actor, array $args): array {
                    app(GradingScaleService::class)->replace($actor, $args['bands']);

                    return ['summary' => 'Grading scale replaced with '.count($args['bands']).' band(s).', 'data' => null];
                }),
        ];
    }
}
