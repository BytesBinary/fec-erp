<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2018, 2026);
        $term = fake()->randomElement(['Spring', 'Fall']);
        $startMonth = $term === 'Spring' ? 1 : 7;

        return [
            'name' => "{$term} {$year}",
            'code' => strtoupper(substr($term, 0, 2)).$year.'-'.fake()->unique()->numerify('###'),
            'starts_on' => sprintf('%d-%02d-01', $year, $startMonth),
            'ends_on' => sprintf('%d-%02d-30', $year, $startMonth + 5),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => true]);
    }
}
