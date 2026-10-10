<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => 'B.Sc. in '.fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('BSC-???')),
            'required_credits' => 160,
            'total_semesters' => 8,
            'is_active' => true,
        ];
    }
}
