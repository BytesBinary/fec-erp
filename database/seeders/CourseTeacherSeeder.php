<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class CourseTeacherSeeder extends Seeder
{
    public function run(): void
    {
        Department::query()->each(function (Department $department): void {
            $teachers = Teacher::where('department_id', $department->id)
                ->with('courses')
                ->get();

            if ($teachers->isEmpty()) {
                return;
            }

            $courses = Course::where('department_id', $department->id)
                ->where('is_active', true)
                ->get();

            $teacherCount = $teachers->count();

            foreach ($courses as $index => $course) {
                // Primary teacher (round-robin across teachers)
                $primary = $teachers[$index % $teacherCount];

                // Secondary teacher (next in round-robin, for variety)
                $secondary = $teachers[($index + 1) % $teacherCount];

                $toAttach = [$primary->id];

                // Attach secondary only if different from primary (avoids duplicate for small teacher pools)
                if ($secondary->id !== $primary->id) {
                    $toAttach[] = $secondary->id;
                }

                $course->teachers()->syncWithoutDetaching($toAttach);
            }
        });
    }
}
