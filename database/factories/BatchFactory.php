<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2010, 2023);

        return [
            'department_id' => Department::factory(),
            'batch_number' => fake()->numberBetween(1, 20),
            'session' => $year.'-'.($year + 1),
            'current_semester' => fake()->numberBetween(1, 8),
        ];
    }
}
