<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalProbe>
 */
class PortalProbeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portal_exam_id' => fake()->numberBetween(2000, 99999),
            'outcome' => 'not_verified',
            'checked_at' => now(),
        ];
    }
}
