<?php

namespace Database\Factories;

use App\Enums\ResultPullStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResultPull>
 */
class ResultPullFactory extends Factory
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
            'trigger' => 'manual',
            'status' => ResultPullStatus::Queued,
            'queued_at' => now(),
        ];
    }
}
