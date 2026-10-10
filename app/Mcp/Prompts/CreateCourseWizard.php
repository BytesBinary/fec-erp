<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class CreateCourseWizard extends Prompt
{
    protected string $name = 'create_course_wizard';

    protected string $title = 'Create a course';

    protected string $description = 'Guided workflow to create a course and offer it in a semester.';

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [new Argument('course_name', 'Name of the course, if already known.', false)];
    }

    public function handle(Request $request): Response
    {
        $name = $request->get('course_name', 'the new course');

        return Response::text(<<<TEXT
            Help the user create {$name}. Steps:
            1. Call department_list and ask which department owns it (department heads can only use their own).
            2. Ask for code, name, semester_number (1-8), type (theory or lab) and credit_hours.
            3. Call course_create with those values (use an idempotencyKey).
            4. Offer to call course_offering_create for the active semester (semester_list) and course_assign_teacher.
            5. Summarise what was created.
            TEXT);
    }
}
