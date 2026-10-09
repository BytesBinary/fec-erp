<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hall>
 */
class HallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lastName().' Hall',
            'code' => strtoupper(fake()->unique()->lexify('H??')),
            'gender' => fake()->randomElement(['male', 'female']),
            'capacity' => fake()->numberBetween(100, 400),
            'is_active' => true,
        ];
    }
}
