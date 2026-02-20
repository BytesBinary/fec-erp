<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'semester_number' => fake()->numberBetween(1, 8),
            'code' => strtoupper(fake()->unique()->bothify('???-###')),
            'name' => fake()->words(4, true),
            'credit_hours' => fake()->randomElement([1.5, 2.0, 3.0, 4.0]),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
