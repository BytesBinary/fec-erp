<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\ToolDefinition;
use App\Services\People\StaffService;
use App\Services\People\StudentService;
use App\Services\People\TeacherService;

/**
 * Students, teachers and staff (profile row + login account).
 */
final class PeopleTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $tools = [
            ...ToolSupport::crud('student', 'student', StudentService::class, 'student', hint: 'Search matches name, email, roll and registration number.'),
            ...ToolSupport::crud('teacher', 'teacher', TeacherService::class, 'teacher'),
            ...ToolSupport::crud('staff', 'staff member', StaffService::class, 'staff'),
        ];

        foreach ($tools as $tool) {
            if ($tool->name === 'student_list') {
                $tool->covers(StudentService::class.'::search');
            }
        }

        return $tools;
    }
}
