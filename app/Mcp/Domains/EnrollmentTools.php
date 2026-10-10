<?php

namespace App\Mcp\Domains;

use App\Enums\AttemptType;
use App\Exceptions\Domain\NotFoundException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Academic\EnrollmentService;

final class EnrollmentTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $attempt = fn (): Param => Param::enum('attempt_type', 'regular, retake or improvement (default regular).', array_map(fn (AttemptType $type): string => $type->value, AttemptType::cases()));

        return [
            ToolDefinition::make('enrollment_list', 'List enrollments', 'List enrollments you may see. Pass student_id for one student, or course_offering_id for one offering; otherwise lists everything in your scope.')
                ->permission('enrollment:list')
                ->params(Param::integer('student_id', 'Only this student.'), Param::integer('course_offering_id', 'Only this offering.'), ...ToolSupport::paging())
                ->covers(EnrollmentService::class.'::query', EnrollmentService::class.'::listFor')
                ->handler(function (User $actor, array $args): array {
                    $service = app(EnrollmentService::class);

                    if (isset($args['student_id']) && ! isset($args['course_offering_id'])) {
                        $rows = $service->listFor($actor, Student::query()->findOrFail($args['student_id']));

                        return ['summary' => $rows->count().' enrollment(s).', 'data' => ['items' => ToolSupport::items($rows), 'next_cursor' => null]];
                    }

                    $query = $service->query($actor)
                        ->when(isset($args['student_id']), fn ($q) => $q->where('student_id', $args['student_id']))
                        ->when(isset($args['course_offering_id']), fn ($q) => $q->where('course_offering_id', $args['course_offering_id']));
                    $page = ToolSupport::paginateQuery($query, $args);

                    return ['summary' => count($page['items']).' enrollment(s).', 'data' => $page];
                }),

            ToolDefinition::make('student_list_my_courses', 'My courses', 'List the signed-in student\'s current course enrollments with course codes and semesters.')
                ->permission('enrollment:list')
                ->covers(EnrollmentService::class.'::myCourses')
                ->handler(function (User $actor): array {
                    $rows = app(EnrollmentService::class)->myCourses($actor);

                    return ['summary' => $rows->count().' course(s).', 'data' => ToolSupport::items($rows)];
                }),

            ToolDefinition::make('enrollment_create', 'Enroll a student', 'Enroll one student in a course offering (regular, retake or improvement attempt). Fails with CONFLICT if already enrolled. Use enrollment_bulk_create for many students.')
                ->permission('enrollment:create')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::integer('course_offering_id', 'The offering id.', required: true), $attempt())
                ->creates()
                ->covers(EnrollmentService::class.'::enroll')
                ->handler(function (User $actor, array $args): array {
                    $enrollment = app(EnrollmentService::class)->enroll(
                        $actor,
                        Student::query()->findOrFail($args['student_id']),
                        CourseOffering::query()->findOrFail($args['course_offering_id']),
                        AttemptType::from($args['attempt_type'] ?? 'regular'),
                    );

                    return ['summary' => "Enrolled student {$args['student_id']} (enrollment {$enrollment->id}).", 'data' => ToolSupport::item($enrollment), 'entity' => ['Enrollment', $enrollment->id]];
                }),

            ToolDefinition::make('enrollment_bulk_create', 'Bulk enroll students', 'Enroll many students in one offering in one all-or-nothing step. Needs confirm=true; without it you get a preview of who would be enrolled.')
                ->permission('enrollment:bulk_create')
                ->params(Param::integer('course_offering_id', 'The offering id.', required: true), Param::array('student_ids', 'Student ids to enroll.', 'integer', true), $attempt())
                ->destructive(fn (User $actor, array $args): array => [
                    'offering' => CourseOffering::query()->with(['course', 'semester'])->findOrFail($args['course_offering_id'])->toArray(),
                    'students' => Student::query()->with('user')->whereIn('id', $args['student_ids'])->get()->map(fn (Student $student): string => $student->roll_number.' '.$student->user->name)->all(),
                ])
                ->covers(EnrollmentService::class.'::bulkEnroll')
                ->handler(function (User $actor, array $args): array {
                    $rows = app(EnrollmentService::class)->bulkEnroll($actor, CourseOffering::query()->findOrFail($args['course_offering_id']), array_map('intval', $args['student_ids']), AttemptType::from($args['attempt_type'] ?? 'regular'));

                    return ['summary' => "Enrolled {$rows->count()} student(s).", 'data' => ToolSupport::items($rows)];
                }),

            ToolDefinition::make('enrollment_drop', 'Drop an enrollment', 'Drop a student from a course offering (not possible once marks were entered). Needs confirm=true.')
                ->permission('enrollment:drop')
                ->params(Param::integer('enrollment_id', 'The enrollment id.', required: true))
                ->destructive(function (User $actor, array $args): array {
                    $enrollment = Enrollment::query()->with(['student.user', 'offering.course'])->find($args['enrollment_id']) ?? throw new NotFoundException('The requested enrollment was not found.');

                    return ['student' => $enrollment->student->user->name, 'course' => $enrollment->offering->course->code, 'effect' => 'The enrollment is marked dropped.'];
                })
                ->covers(EnrollmentService::class.'::drop')
                ->handler(function (User $actor, array $args): array {
                    $enrollment = app(EnrollmentService::class)->drop($actor, Enrollment::query()->findOrFail($args['enrollment_id']));

                    return ['summary' => 'Enrollment dropped.', 'data' => ToolSupport::item($enrollment), 'entity' => ['Enrollment', $enrollment->id]];
                }),
        ];
    }
}
