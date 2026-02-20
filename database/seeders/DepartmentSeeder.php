<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Computer Science & Engineering', 'code' => 'CSE', 'description' => 'Department of Computer Science and Engineering'],
            ['name' => 'Electrical & Electronic Engineering', 'code' => 'EEE', 'description' => 'Department of Electrical and Electronic Engineering'],
            ['name' => 'Civil Engineering', 'code' => 'CE', 'description' => 'Department of Civil Engineering'],
            ['name' => 'Mechanical Engineering', 'code' => 'ME', 'description' => 'Department of Mechanical Engineering'],
            ['name' => 'Electronics & Telecommunication Engineering', 'code' => 'ETE', 'description' => 'Department of Electronics and Telecommunication Engineering'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                array_merge($department, ['is_active' => true])
            );
        }
    }
}
