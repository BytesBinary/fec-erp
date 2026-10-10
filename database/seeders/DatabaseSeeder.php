<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeded students are fixtures, not people to look up on the exam portal.
        config(['result_portal.enabled' => false, 'notifications.enabled' => false]);

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
            ClearanceStageSeeder::class,
            ProfileRequiredFieldSeeder::class,
        ]);
    }
}
