<?php

namespace Database\Factories;

use App\Enums\PortalExamKind;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalResult>
 */
class PortalResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'portal_exam_id' => fake()->unique()->numberBetween(1, 9999),
            'exam_title' => 'B.Sc. in Computer Science and Engineering 1st year 1st Semester Examination of 2024',
            'exam_kind' => PortalExamKind::Regular,
            'course_code' => 'CSE-'.fake()->numerify('####'),
            'letter' => 'A',
            'grade_point' => 4.0,
            'is_current' => true,
            'fetched_at' => now(),
        ];
    }
}
