<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    /**
     * Students grouped by department code, seeded into that department's
     * newest batch. Each entry: [name, email, rollNumber, registrationNumber]
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: string, 3: string}>>
     */
    private const STUDENTS = [
        'CSE' => [
            ['Nafis Ahmed', 'nafis.ahmed@student.fec.edu.bd', '2412001', 'FEC-2024-1001'],
            ['Tasnim Jahan', 'tasnim.jahan@student.fec.edu.bd', '2412002', 'FEC-2024-1002'],
            ['Rakibul Hasan', 'rakibul.hasan@student.fec.edu.bd', '2412003', 'FEC-2024-1003'],
        ],
        'EEE' => [
            ['Mahmudul Hasan', 'mahmudul.hasan@student.fec.edu.bd', '2412004', 'FEC-2024-1004'],
            ['Sumaiya Islam', 'sumaiya.islam@student.fec.edu.bd', '2412005', 'FEC-2024-1005'],
            ['Arafat Hossain', 'arafat.hossain@student.fec.edu.bd', '2412006', 'FEC-2024-1006'],
        ],
        'CE' => [
            ['Jannatul Ferdous', 'jannatul.ferdous@student.fec.edu.bd', '2412007', 'FEC-2024-1007'],
            ['Sabbir Ahmed', 'sabbir.ahmed@student.fec.edu.bd', '2412008', 'FEC-2024-1008'],
            ['Nusrat Jahan', 'nusrat.jahan@student.fec.edu.bd', '2412009', 'FEC-2024-1009'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::pluck('id', 'code');

        foreach (self::STUDENTS as $deptCode => $studentList) {
            $departmentId = $departments[$deptCode] ?? null;

            if ($departmentId === null) {
                continue;
            }

            $batchId = Batch::where('department_id', $departmentId)
                ->orderByDesc('batch_number')
                ->value('id');

            if ($batchId === null) {
                continue;
            }

            foreach ($studentList as [$name, $email, $rollNumber, $registrationNumber]) {
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                    ]
                );

                Student::updateOrCreate(
                    ['registration_number' => $registrationNumber],
                    [
                        'user_id' => $user->id,
                        'department_id' => $departmentId,
                        'batch_id' => $batchId,
                        'roll_number' => $rollNumber,
                        'current_semester' => 1,
                        'phone' => null,
                    ]
                );

                $user->syncRoles('student');
            }
        }
    }
}
