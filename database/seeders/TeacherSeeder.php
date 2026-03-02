<?php

namespace Database\Seeders;

use App\Enums\DesignationType;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    /**
     * Teachers grouped by department code.
     * Each entry: [name, email, employeeId, shortName, designationName]
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>>
     */
    private array $teachers = [
        'CSE' => [
            ['Md. Suman Reza',        'suman.reza@fec.edu.bd',        'CSE001', 'MSR', 'Lecturer'],
            ['Md. Rasel Ahmed',        'rasel.ahmed@fec.edu.bd',       'CSE002', 'MRA', 'Lecturer'],
            ['Zannatun Naeem',         'zannatun.naeem@fec.edu.bd',    'CSE003', 'ZN',  'Lecturer'],
            ['Chandra Mohan Halder',   'chandra.halder@fec.edu.bd',    'CSE004', 'CMH', 'Lecturer'],
            ['Sameya Akter',           'sameya.akter@fec.edu.bd',      'CSE005', 'SA',  'Lecturer'],
        ],
        'EEE' => [
            ['Md. Zillur Rahman',      'zillur.rahman@fec.edu.bd',     'EEE001', 'MZR', 'Lecturer'],
            ['Md. Shah Jamal Molla',   'shahjamol.molla@fec.edu.bd',   'EEE002', 'MSJ', 'Lecturer'],
            ['Md. Rany Ahmed',         'rany.ahmed@fec.edu.bd',        'EEE003', 'MRA', 'Lecturer'],
            ['Ayesha Akter',           'ayesha.akter@fec.edu.bd',      'EEE004', 'AA',  'Lecturer'],
            ['Afia Begum',             'afia.begum@fec.edu.bd',        'EEE005', 'AB',  'Lecturer'],
        ],
        'CE' => [
            ['Mohammad Shamsul Islam', 'shamsul.islam@fec.edu.bd',     'CE001',  'MSI', 'Lecturer'],
            ['Md. Tuhin Reza',         'tuhin.reza@fec.edu.bd',        'CE002',  'MTR', 'Lecturer'],
            ['Md. Ekhlas Uddin',       'ekhlas.uddin@fec.edu.bd',      'CE003',  'MEU', 'Lecturer'],
            ['Muhammad Younus Ali',    'younus.ali@fec.edu.bd',        'CE004',  'MYA', 'Lecturer'],
        ],
        'ME' => [
            ['Md. Fahad Hossain',      'fahad.hossain@fec.edu.bd',     'ME001',  'MFH', 'Lecturer'],
            ['Puja Brahma',            'puja.brahma@fec.edu.bd',       'ME002',  'PB',  'Lecturer'],
            ['Partha Mandal',          'partha.mandal@fec.edu.bd',     'ME003',  'PM',  'Lecturer'],
            ['Apurbo Biswas',          'apurbo.biswas@fec.edu.bd',     'ME004',  'APB', 'Lecturer'],
        ],
        'ETE' => [
            ['Foyshal Ahmed',          'foyshal.ahmed@fec.edu.bd',     'ETE001', 'FA',  'Lecturer'],
            ['Md. Afser Jani',         'afser.jani@fec.edu.bd',        'ETE002', 'MAJ', 'Lecturer'],
            ['Md. Shihab Uddin',       'shihab.uddin@fec.edu.bd',      'ETE003', 'MSU', 'Lecturer'],
            ['Md. Shohanur Rahman',    'shohanur.rahman@fec.edu.bd',   'ETE004', 'MSHR', 'Lecturer'],
        ],
    ];

    public function run(): void
    {
        $departments = Department::pluck('id', 'code');
        $designations = Designation::where('type', DesignationType::Teacher)->pluck('id', 'name');

        foreach ($this->teachers as $deptCode => $teacherList) {
            $departmentId = $departments[$deptCode] ?? null;

            if ($departmentId === null) {
                continue;
            }

            foreach ($teacherList as [$name, $email, $employeeId, $shortName, $designationName]) {
                $designationId = $designations[$designationName] ?? null;

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                    ]
                );

                Teacher::updateOrCreate(
                    ['employee_id' => $employeeId],
                    [
                        'user_id' => $user->id,
                        'department_id' => $departmentId,
                        'designation_id' => $designationId,
                        'short_name' => $shortName,
                        'joining_date' => '2020-01-01',
                        'phone' => null,
                    ]
                );
            }
        }
    }
}
