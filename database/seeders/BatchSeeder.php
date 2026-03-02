<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Department;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $batches = [
            ['batch_number' => 8, 'session' => '2020-2021', 'current_semester' => 8],
            ['batch_number' => 9, 'session' => '2021-2022', 'current_semester' => 6],
            ['batch_number' => 10, 'session' => '2022-2023', 'current_semester' => 4],
            ['batch_number' => 11, 'session' => '2023-2024', 'current_semester' => 2],
            ['batch_number' => 12, 'session' => '2024-2025', 'current_semester' => 1],
        ];

        Department::query()->each(function (Department $department) use ($batches): void {
            foreach ($batches as $batch) {
                Batch::firstOrCreate(
                    ['department_id' => $department->id, 'batch_number' => $batch['batch_number']],
                    array_merge($batch, ['department_id' => $department->id])
                );
            }
        });
    }
}
