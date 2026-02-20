<?php

namespace Database\Seeders;

use App\Enums\DesignationType;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    /**
     * Teachers indexed by department code.
     * Each entry: [name, email, employeeId, shortName, designation (Prof/Assoc/Asst/Lec)]
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>>
     */
    private array $teachers = [
        'CSE' => [
            ['Dr. Md. Rahman Chowdhury', 'rahman.cse@fec.edu.bd', 'CSE001', 'MRC', 'Professor'],
            ['Dr. Nur Islam Miah', 'nurislam.cse@fec.edu.bd', 'CSE002', 'NIM', 'Associate Professor'],
            ['Md. Hasan Ali', 'hasan.cse@fec.edu.bd', 'CSE003', 'MHA', 'Assistant Professor'],
            ['Farhana Begum', 'farhana.cse@fec.edu.bd', 'CSE004', 'FB', 'Lecturer'],
        ],
        'EEE' => [
            ['Dr. Aminul Haque', 'aminul.eee@fec.edu.bd', 'EEE001', 'AH', 'Professor'],
            ['Dr. Shahidul Islam', 'shahidul.eee@fec.edu.bd', 'EEE002', 'SI', 'Associate Professor'],
            ['Md. Rofiqul Islam', 'rofiqul.eee@fec.edu.bd', 'EEE003', 'RI', 'Assistant Professor'],
            ['Nasrin Akter', 'nasrin.eee@fec.edu.bd', 'EEE004', 'NA', 'Lecturer'],
        ],
        'CE' => [
            ['Dr. Abdul Karim', 'karim.ce@fec.edu.bd', 'CE001', 'AK', 'Professor'],
            ['Md. Mostafizur Rahman', 'mostafiz.ce@fec.edu.bd', 'CE002', 'MMR', 'Associate Professor'],
            ['Md. Jahangir Alam', 'jahangir.ce@fec.edu.bd', 'CE003', 'MJA', 'Assistant Professor'],
            ['Roksana Parvin', 'roksana.ce@fec.edu.bd', 'CE004', 'RP', 'Lecturer'],
        ],
        'ME' => [
            ['Dr. Syed Mahbubur Rahman', 'mahbubur.me@fec.edu.bd', 'ME001', 'SMR', 'Professor'],
            ['Md. Khairul Islam', 'khairul.me@fec.edu.bd', 'ME002', 'MKI', 'Associate Professor'],
            ['Md. Shamsul Alam', 'shamsul.me@fec.edu.bd', 'ME003', 'MSA', 'Assistant Professor'],
            ['Sultana Razia', 'sultana.me@fec.edu.bd', 'ME004', 'SR', 'Lecturer'],
        ],
        'ETE' => [
            ['Dr. Md. Rafiqul Islam', 'rafiqul.ete@fec.edu.bd', 'ETE001', 'MRI', 'Professor'],
            ['Md. Moniruzzaman', 'monir.ete@fec.edu.bd', 'ETE002', 'MM', 'Associate Professor'],
            ['Md. Kamrul Hasan', 'kamrul.ete@fec.edu.bd', 'ETE003', 'MKH', 'Assistant Professor'],
            ['Tahmina Akter', 'tahmina.ete@fec.edu.bd', 'ETE004', 'TA', 'Lecturer'],
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

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'role' => UserRole::Teacher,
                    ]
                );

                Teacher::firstOrCreate(
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
