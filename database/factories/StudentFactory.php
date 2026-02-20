<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'department_id' => Department::factory(),
            'batch_id' => Batch::factory(),
            'roll_number' => fake()->unique()->numerify('##########'),
            'registration_number' => fake()->unique()->numerify('FEC-####-####'),
            'current_semester' => fake()->numberBetween(1, 8),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
