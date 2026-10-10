<?php

namespace App\Mcp\Domains;

use App\Exceptions\Domain\NotFoundException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Profile\ProfileService;
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
