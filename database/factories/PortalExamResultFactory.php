<?php

namespace Database\Factories;

use App\Enums\PortalExamKind;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalExamResult>
 */
class PortalExamResultFactory extends Factory
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
            'portal_exam_id' => fake()->unique()->numberBetween(2000, 99999),
            'exam_title' => 'B.Sc. in Computer Science and Engineering 1st year 1st Semester Examination of 2023',
            'exam_kind' => PortalExamKind::Regular,
            'exam_year' => 2023,
            'outcome' => 'Promoted',
            'fetched_at' => now(),
        ];
    }
}
