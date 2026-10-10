<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailDelivery>
 */
class EmailDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => 'security.password_changed',
            'recipient_email' => fake()->safeEmail(),
            'subject' => 'Subject',
            'body' => 'Body',
            'status' => EmailDeliveryStatus::Queued,
            'dedupe_key' => fake()->unique()->uuid(),
            'queued_at' => now(),
        ];
    }
}
