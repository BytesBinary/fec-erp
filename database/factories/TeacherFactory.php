<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'department_id' => Department::factory(),
            'employee_id' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'designation' => fake()->randomElement(['Lecturer', 'Assistant Professor', 'Associate Professor', 'Professor']),
            'joining_date' => fake()->dateTimeBetween('-10 years', 'now'),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
