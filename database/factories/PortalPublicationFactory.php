<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalPublication>
 */
class PortalPublicationFactory extends Factory
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
            'status' => 'detected',
            'mode' => 'live',
            'detected_at' => now(),
        ];
    }
}
