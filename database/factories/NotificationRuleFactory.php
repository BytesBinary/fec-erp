<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => fake()->unique()->slug(2),
            'category' => 'security',
            'enabled' => true,
            'mode' => 'immediate',
            'recipients' => ['affected_user'],
        ];
    }
}
