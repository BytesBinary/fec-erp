<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            // Theory periods: 8:50 AM – 1:10 PM (5 × 50-min periods, with one 10-min break after period 3)
            ['name' => 'Period 1', 'start_time' => '08:50:00', 'end_time' => '09:40:00', 'type' => CourseType::Theory, 'sort_order' => 1],
            ['name' => 'Period 2', 'start_time' => '09:40:00', 'end_time' => '10:30:00', 'type' => CourseType::Theory, 'sort_order' => 2],
            ['name' => 'Period 3', 'start_time' => '10:30:00', 'end_time' => '11:20:00', 'type' => CourseType::Theory, 'sort_order' => 3],
            ['name' => 'Period 4', 'start_time' => '11:30:00', 'end_time' => '12:20:00', 'type' => CourseType::Theory, 'sort_order' => 4],
            ['name' => 'Period 5', 'start_time' => '12:20:00', 'end_time' => '13:10:00', 'type' => CourseType::Theory, 'sort_order' => 5],
            // Break: 1:10 PM – 2:00 PM
            ['name' => 'Break', 'start_time' => '13:10:00', 'end_time' => '14:00:00', 'type' => CourseType::Break, 'sort_order' => 6],
            // Lab slot: 2:00 PM – 4:00 PM
            ['name' => 'Lab', 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'type' => CourseType::Lab, 'sort_order' => 7],
        ];

        foreach ($slots as $slot) {
            TimeSlot::firstOrCreate(
                ['name' => $slot['name'], 'type' => $slot['type']],
                $slot
            );
        }
    }
}
