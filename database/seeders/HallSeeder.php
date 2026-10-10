<?php

namespace Database\Seeders;

use App\Models\Hall;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    /**
     * Residential halls (hostels).
     */
    public function run(): void
    {
        $halls = [
            ['code' => 'BH', 'name' => 'Boys Hall', 'gender' => 'male', 'capacity' => 300],
            ['code' => 'GH', 'name' => 'Girls Hall', 'gender' => 'female', 'capacity' => 200],
        ];

        foreach ($halls as $hall) {
            Hall::firstOrCreate(['code' => $hall['code']], [...$hall, 'is_active' => true]);
        }
    }
}
