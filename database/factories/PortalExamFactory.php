<?php

namespace Database\Factories;

use App\Enums\PortalExamKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalExam>
 */
class PortalExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portal_exam_id' => fake()->unique()->numberBetween(2000, 99999),
            'program_id' => 14,
            'title' => 'B.Sc. in Computer Science and Engineering 1st year 1st Semester Examination of 2023',
            'kind' => PortalExamKind::Regular,
            'semester' => 1,
            'exam_year' => 2023,
            'status' => 'known',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
