<?php

namespace Database\Seeders;

use App\Enums\DesignationType;
use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            // Teacher designations
            ['name' => 'Professor', 'short_name' => 'Prof.', 'type' => DesignationType::Teacher],
            ['name' => 'Associate Professor', 'short_name' => 'Assoc. Prof.', 'type' => DesignationType::Teacher],
            ['name' => 'Assistant Professor', 'short_name' => 'Asst. Prof.', 'type' => DesignationType::Teacher],
            ['name' => 'Lecturer', 'short_name' => 'Lec.', 'type' => DesignationType::Teacher],
            ['name' => 'Lab Instructor', 'short_name' => 'Lab. Ins.', 'type' => DesignationType::Teacher],

            // Staff designations
            ['name' => 'Principal', 'short_name' => 'Principal', 'type' => DesignationType::Staff],
            ['name' => 'Vice Principal', 'short_name' => 'V. Principal', 'type' => DesignationType::Staff],
            ['name' => 'Administrative Officer', 'short_name' => 'Admin. Officer', 'type' => DesignationType::Staff],
            ['name' => 'Accountant', 'short_name' => 'Accountant', 'type' => DesignationType::Staff],
            ['name' => 'Office Assistant', 'short_name' => 'Office Asst.', 'type' => DesignationType::Staff],
            ['name' => 'Librarian', 'short_name' => 'Librarian', 'type' => DesignationType::Staff],
        ];

        foreach ($designations as $designation) {
            Designation::firstOrCreate(
                ['name' => $designation['name']],
                array_merge($designation, ['is_active' => true])
            );
        }
    }
}
