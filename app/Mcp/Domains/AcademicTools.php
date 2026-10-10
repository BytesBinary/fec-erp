<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\Course;
use App\Models\User;
use App\Services\Academic\BatchService;
use App\Services\Academic\CourseOfferingService;
use App\Services\Academic\CourseService;
use App\Services\Academic\DepartmentService;
use App\Services\Academic\DesignationService;
use App\Services\Academic\ProgramService;
use App\Services\Academic\SemesterService;
use App\Services\Halls\HallService;

/**
 * Academic structure: departments, programs, semesters, courses, offerings,
 * batches, designations and halls.
 */
final class AcademicTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        return [
            ...ToolSupport::crud('department', 'department', DepartmentService::class, 'department'),
            ...ToolSupport::crud('program', 'program', ProgramService::class, 'program'),
            ...ToolSupport::crud('semester', 'semester', SemesterService::class, 'semester'),

            ToolDefinition::make('semester_set_active', 'Set active semester', 'Make a semester the active one (only one is active at a time; the previous one is switched off).')
                ->permission('semester:activate')
                ->params(Param::integer('id', 'The semester id.', required: true))
                ->write(idempotent: true)
                ->covers(SemesterService::class.'::setActive')
                ->handler(function (User $actor, array $args): array {
                    $semester = app(SemesterService::class)->setActive($actor, (int) $args['id']);

                    return ['summary' => "{$semester->name} is now the active semester.", 'data' => ToolSupport::item($semester), 'entity' => ['Semester', $semester->id]];
                }),

            ...ToolSupport::crud('course', 'course', CourseService::class, 'course', hint: 'Department heads can only manage courses of their own department.'),

            ToolDefinition::make('course_archive', 'Archive course', 'Archive (deactivate) a course without deleting it. Needs confirm=true.')
                ->permission('course:archive')
                ->params(Param::integer('id', 'The course id.', required: true))
                ->destructive(fn (User $actor, array $args): array => ['course' => Course::query()->findOrFail($args['id'])->only(['id', 'code', 'name']), 'effect' => 'The course becomes inactive and is no longer offered.'])
                ->covers(CourseService::class.'::archive')
                ->handler(function (User $actor, array $args): array {
                    $course = app(CourseService::class)->archive($actor, (int) $args['id']);

                    return ['summary' => "Archived {$course->code}.", 'data' => ToolSupport::item($course), 'entity' => ['Course', $course->id]];
                }),

            ToolDefinition::make('course_assign_teacher', 'Assign teachers to a course', 'Set the teachers of a course (replaces the current assignment). Teacher ids come from teacher_list.')
                ->permission('course:assign_teacher')
                ->params(Param::integer('course_id', 'The course id.', required: true), Param::array('teacher_ids', 'Teacher ids that should teach the course.', 'integer', true))
                ->write(idempotent: true)
                ->covers(CourseService::class.'::assignTeachers')
                ->handler(function (User $actor, array $args): array {
                    $course = app(CourseService::class)->assignTeachers($actor, (int) $args['course_id'], array_map('intval', $args['teacher_ids']));

                    return ['summary' => "Updated the teachers of {$course->code}.", 'data' => $course->load('teachers')->toArray(), 'entity' => ['Course', $course->id]];
                }),

            ToolDefinition::make('course_offering_list', 'List course offerings', 'List course offerings (course x semester x section) you may see. Filter by semester_id or course_id.')
                ->permission('course_offering:list')
                ->params(Param::integer('semester_id', 'Only this semester.'), Param::integer('course_id', 'Only this course.'), ...ToolSupport::paging())
                ->covers(CourseOfferingService::class.'::query')
                ->handler(function (User $actor, array $args): array {
                    $query = app(CourseOfferingService::class)->query($actor)
                        ->when(isset($args['semester_id']), fn ($q) => $q->where('semester_id', $args['semester_id']))
                        ->when(isset($args['course_id']), fn ($q) => $q->where('course_id', $args['course_id']));
                    $page = ToolSupport::paginateQuery($query, $args);

                    return ['summary' => count($page['items']).' offering(s).', 'data' => $page];
                }),

            ToolDefinition::make('course_offering_get', 'Get course offering', 'Get one course offering (a course delivered in a semester and section) by id, with its teacher. Use after course_offering_list.')
                ->permission('course_offering:list')
                ->params(Param::integer('id', 'The offering id.', required: true))
                ->covers(CourseOfferingService::class.'::get')
                ->handler(fn (User $actor, array $args): array => ['summary' => 'Course offering.', 'data' => ToolSupport::item(app(CourseOfferingService::class)->get($actor, (int) $args['id']))]),

            ToolDefinition::make('course_offering_create', 'Create course offering', 'Offer a course in a semester (and section, default A), optionally with its teacher. Fails with CONFLICT if it already exists.')
                ->permission('course_offering:create')
                ->params(Param::integer('course_id', 'The course id.', required: true), Param::integer('semester_id', 'The semester id.', required: true), Param::string('section', 'Section, default A.'), Param::integer('teacher_id', 'Teacher id.'))
                ->creates()
                ->covers(CourseOfferingService::class.'::create')
                ->handler(function (User $actor, array $args): array {
                    $offering = app(CourseOfferingService::class)->create($actor, (int) $args['course_id'], (int) $args['semester_id'], $args['section'] ?? 'A', isset($args['teacher_id']) ? (int) $args['teacher_id'] : null);

                    return ['summary' => "Created offering {$offering->id}.", 'data' => ToolSupport::item($offering), 'entity' => ['CourseOffering', $offering->id]];
                }),

            ...ToolSupport::crud('batch', 'batch', BatchService::class, 'batch'),
            ...ToolSupport::crud('designation', 'designation', DesignationService::class, 'designation'),
            ...ToolSupport::crud('hall', 'hall', HallService::class, 'hall'),
        ];
    }
}
