<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstitutionSettingSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
            DepartmentSeeder::class,
            HallSeeder::class,
            DesignationSeeder::class,
            TimeSlotSeeder::class,
            BatchSeeder::class,
            CourseSeeder::class,
            TeacherSeeder::class,
            CourseTeacherSeeder::class,
            StaffSeeder::class,
            StudentSeeder::class,
            GradingScaleSeeder::class,
            ProfileRequiredFieldSeeder::class,
        ]);
    }
}
