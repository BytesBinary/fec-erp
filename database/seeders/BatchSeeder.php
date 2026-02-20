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
            ['batch_number' => 46, 'session' => '2019-2020', 'current_semester' => 7],
            ['batch_number' => 47, 'session' => '2020-2021', 'current_semester' => 5],
            ['batch_number' => 48, 'session' => '2021-2022', 'current_semester' => 3],
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
