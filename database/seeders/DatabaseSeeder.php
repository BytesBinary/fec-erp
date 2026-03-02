<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstitutionSettingSeeder::class,
            AdminUserSeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            TimeSlotSeeder::class,
            BatchSeeder::class,
            CourseSeeder::class,
            TeacherSeeder::class,
        ]);
    }
}
