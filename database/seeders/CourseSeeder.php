<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Courses indexed by dept code → semester → [code, name, credits, type].
     * Type defaults to Theory when omitted.
     *
     * @var array<string, array<int, array<int, array{0: string, 1: string, 2: float, 3?: CourseType}>>>
     */
    private array $courses = [
        'CSE' => [
            1 => [
                ['CSE101', 'Mathematics I', 3.0],
                ['CSE102', 'Physics I', 3.0],
                ['CSE103', 'English & Communication Skills', 2.0],
                ['CSE104', 'Physics Sessional', 1.5, CourseType::Lab],
            ],
            2 => [
                ['CSE201', 'Mathematics II', 3.0],
                ['CSE202', 'Chemistry', 3.0],
                ['CSE203', 'Programming Fundamentals', 3.0],
                ['CSE204', 'Programming Sessional', 1.5, CourseType::Lab],
            ],
            3 => [
                ['CSE301', 'Data Structures', 3.0],
                ['CSE302', 'Digital Electronics', 3.0],
                ['CSE303', 'Discrete Mathematics', 3.0],
                ['CSE304', 'Data Structures Sessional', 1.5, CourseType::Lab],
            ],
            4 => [
                ['CSE401', 'Algorithms', 3.0],
                ['CSE402', 'Computer Organization & Architecture', 3.0],
                ['CSE403', 'Object-Oriented Programming', 3.0],
                ['CSE404', 'OOP Sessional', 1.5, CourseType::Lab],
            ],
            5 => [
                ['CSE501', 'Operating Systems', 3.0],
                ['CSE502', 'Database Management Systems', 3.0],
                ['CSE503', 'Computer Networks', 3.0],
                ['CSE504', 'OS & Networks Sessional', 1.5, CourseType::Lab],
            ],
            6 => [
                ['CSE601', 'Software Engineering', 3.0],
                ['CSE602', 'Compiler Design', 3.0],
                ['CSE603', 'Microprocessors & Assembly Language', 3.0],
                ['CSE604', 'Microprocessors Sessional', 1.5, CourseType::Lab],
            ],
            7 => [
                ['CSE701', 'Artificial Intelligence', 3.0],
                ['CSE702', 'Machine Learning', 3.0],
                ['CSE703', 'Computer Graphics & Image Processing', 3.0],
                ['CSE704', 'AI & ML Sessional', 1.5, CourseType::Lab],
            ],
            8 => [
                ['CSE801', 'Distributed Systems', 3.0],
                ['CSE802', 'Information Security', 3.0],
                ['CSE803', 'Project Work', 3.0],
                ['CSE804', 'Seminar', 1.5, CourseType::Lab],
            ],
        ],
        'EEE' => [
            1 => [
                ['EEE101', 'Mathematics I', 3.0],
                ['EEE102', 'Physics I', 3.0],
                ['EEE103', 'Chemistry', 3.0],
                ['EEE104', 'Physics Sessional', 1.5, CourseType::Lab],
            ],
            2 => [
                ['EEE201', 'Mathematics II', 3.0],
                ['EEE202', 'Electrical Circuits I', 3.0],
                ['EEE203', 'Engineering Drawing', 2.0],
                ['EEE204', 'Electrical Circuits Sessional', 1.5, CourseType::Lab],
            ],
            3 => [
                ['EEE301', 'Electrical Circuits II', 3.0],
                ['EEE302', 'Electronics I', 3.0],
                ['EEE303', 'Electromagnetic Fields & Waves', 3.0],
                ['EEE304', 'Electronics Sessional', 1.5, CourseType::Lab],
            ],
            4 => [
                ['EEE401', 'Electronics II', 3.0],
                ['EEE402', 'Digital Electronics', 3.0],
                ['EEE403', 'Control Systems', 3.0],
                ['EEE404', 'Control Systems Sessional', 1.5, CourseType::Lab],
            ],
            5 => [
                ['EEE501', 'Power Systems I', 3.0],
                ['EEE502', 'Communication Systems', 3.0],
                ['EEE503', 'Microprocessors', 3.0],
                ['EEE504', 'Power Systems Sessional', 1.5, CourseType::Lab],
            ],
            6 => [
                ['EEE601', 'Power Systems II', 3.0],
                ['EEE602', 'Digital Signal Processing', 3.0],
                ['EEE603', 'Power Electronics', 3.0],
                ['EEE604', 'Signal Processing Sessional', 1.5, CourseType::Lab],
            ],
            7 => [
                ['EEE701', 'Power System Protection', 3.0],
                ['EEE702', 'Advanced Electronics', 3.0],
                ['EEE703', 'Industrial Electronics', 3.0],
                ['EEE704', 'Power Lab', 1.5, CourseType::Lab],
            ],
            8 => [
                ['EEE801', 'Power Station Engineering', 3.0],
                ['EEE802', 'High Voltage Engineering', 3.0],
                ['EEE803', 'Project Work', 3.0],
                ['EEE804', 'Seminar', 1.5, CourseType::Lab],
            ],
        ],
        'CE' => [
            1 => [
                ['CE101', 'Mathematics I', 3.0],
                ['CE102', 'Physics', 3.0],
                ['CE103', 'Chemistry', 3.0],
                ['CE104', 'Surveying Sessional', 1.5, CourseType::Lab],
            ],
            2 => [
                ['CE201', 'Mathematics II', 3.0],
                ['CE202', 'Engineering Mechanics', 3.0],
                ['CE203', 'Engineering Drawing', 2.0],
                ['CE204', 'Engineering Drawing Sessional', 1.5, CourseType::Lab],
            ],
            3 => [
                ['CE301', 'Mechanics of Solids', 3.0],
                ['CE302', 'Fluid Mechanics', 3.0],
                ['CE303', 'Building Materials & Construction', 3.0],
                ['CE304', 'Fluid Mechanics Sessional', 1.5, CourseType::Lab],
            ],
            4 => [
                ['CE401', 'Structural Analysis I', 3.0],
                ['CE402', 'Soil Mechanics', 3.0],
                ['CE403', 'Concrete Technology', 3.0],
                ['CE404', 'Soil Mechanics Sessional', 1.5, CourseType::Lab],
            ],
            5 => [
                ['CE501', 'Structural Analysis II', 3.0],
                ['CE502', 'Transportation Engineering', 3.0],
                ['CE503', 'Hydrology & Water Resources', 3.0],
                ['CE504', 'Structural Sessional', 1.5, CourseType::Lab],
            ],
            6 => [
                ['CE601', 'Foundation Engineering', 3.0],
                ['CE602', 'Environmental Engineering', 3.0],
                ['CE603', 'Reinforced Concrete Design', 3.0],
                ['CE604', 'Environmental Sessional', 1.5, CourseType::Lab],
            ],
            7 => [
                ['CE701', 'Steel Structures', 3.0],
                ['CE702', 'Highway & Traffic Engineering', 3.0],
                ['CE703', 'Hydraulic Structures', 3.0],
                ['CE704', 'Steel Structures Sessional', 1.5, CourseType::Lab],
            ],
            8 => [
                ['CE801', 'Bridge Engineering', 3.0],
                ['CE802', 'Earthquake Engineering', 3.0],
                ['CE803', 'Project Work', 3.0],
                ['CE804', 'Seminar', 1.5, CourseType::Lab],
            ],
        ],
        'ME' => [
            1 => [
                ['ME101', 'Mathematics I', 3.0],
                ['ME102', 'Physics', 3.0],
                ['ME103', 'Chemistry', 3.0],
                ['ME104', 'Workshop Practice', 1.5, CourseType::Lab],
            ],
            2 => [
                ['ME201', 'Mathematics II', 3.0],
                ['ME202', 'Engineering Mechanics', 3.0],
                ['ME203', 'Engineering Drawing', 2.0],
                ['ME204', 'Engineering Drawing Sessional', 1.5, CourseType::Lab],
            ],
            3 => [
                ['ME301', 'Thermodynamics I', 3.0],
                ['ME302', 'Mechanics of Materials', 3.0],
                ['ME303', 'Manufacturing Processes', 3.0],
                ['ME304', 'Thermodynamics Sessional', 1.5, CourseType::Lab],
            ],
            4 => [
                ['ME401', 'Thermodynamics II', 3.0],
                ['ME402', 'Machine Design I', 3.0],
                ['ME403', 'Fluid Mechanics', 3.0],
                ['ME404', 'Fluid Mechanics Sessional', 1.5, CourseType::Lab],
            ],
            5 => [
                ['ME501', 'Heat Transfer', 3.0],
                ['ME502', 'Machine Design II', 3.0],
                ['ME503', 'Dynamics of Machinery', 3.0],
                ['ME504', 'Heat Transfer Sessional', 1.5, CourseType::Lab],
            ],
            6 => [
                ['ME601', 'Internal Combustion Engines', 3.0],
                ['ME602', 'Manufacturing Technology', 3.0],
                ['ME603', 'Control Engineering', 3.0],
                ['ME604', 'Manufacturing Sessional', 1.5, CourseType::Lab],
            ],
            7 => [
                ['ME701', 'Industrial Engineering', 3.0],
                ['ME702', 'Refrigeration & Air Conditioning', 3.0],
                ['ME703', 'Power Plant Engineering', 3.0],
                ['ME704', 'Industrial Engineering Sessional', 1.5, CourseType::Lab],
            ],
            8 => [
                ['ME801', 'Advanced Manufacturing', 3.0],
                ['ME802', 'Renewable Energy Systems', 3.0],
                ['ME803', 'Project Work', 3.0],
                ['ME804', 'Seminar', 1.5, CourseType::Lab],
            ],
        ],
        'ETE' => [
            1 => [
                ['ETE101', 'Mathematics I', 3.0],
                ['ETE102', 'Physics I', 3.0],
                ['ETE103', 'Chemistry', 3.0],
                ['ETE104', 'Physics Sessional', 1.5, CourseType::Lab],
            ],
            2 => [
                ['ETE201', 'Mathematics II', 3.0],
                ['ETE202', 'Electronics I', 3.0],
                ['ETE203', 'Engineering Drawing', 2.0],
                ['ETE204', 'Electronics Sessional', 1.5, CourseType::Lab],
            ],
            3 => [
                ['ETE301', 'Electronics II', 3.0],
                ['ETE302', 'Digital Electronics', 3.0],
                ['ETE303', 'Electromagnetic Theory', 3.0],
                ['ETE304', 'Digital Electronics Sessional', 1.5, CourseType::Lab],
            ],
            4 => [
                ['ETE401', 'Signals & Systems', 3.0],
                ['ETE402', 'Communication Theory', 3.0],
                ['ETE403', 'Microelectronics', 3.0],
                ['ETE404', 'Signals & Systems Sessional', 1.5, CourseType::Lab],
            ],
            5 => [
                ['ETE501', 'Analog Communication', 3.0],
                ['ETE502', 'Digital Communication', 3.0],
                ['ETE503', 'Antenna Theory & Design', 3.0],
                ['ETE504', 'Communication Sessional', 1.5, CourseType::Lab],
            ],
            6 => [
                ['ETE601', 'Mobile Communications', 3.0],
                ['ETE602', 'Optical Fiber Communications', 3.0],
                ['ETE603', 'Microprocessors & Interfacing', 3.0],
                ['ETE604', 'Mobile Comms Sessional', 1.5, CourseType::Lab],
            ],
            7 => [
                ['ETE701', 'Satellite Communication', 3.0],
                ['ETE702', 'Network Architecture & Protocols', 3.0],
                ['ETE703', 'Digital Signal Processing', 3.0],
                ['ETE704', 'DSP Sessional', 1.5, CourseType::Lab],
            ],
            8 => [
                ['ETE801', 'Advanced Communication Systems', 3.0],
                ['ETE802', 'Wireless Networks', 3.0],
                ['ETE803', 'Project Work', 3.0],
                ['ETE804', 'Seminar', 1.5, CourseType::Lab],
            ],
        ],
    ];

    public function run(): void
    {
        $departments = Department::pluck('id', 'code');

        foreach ($this->courses as $deptCode => $semesterCourses) {
            $departmentId = $departments[$deptCode] ?? null;

            if ($departmentId === null) {
                continue;
            }

            foreach ($semesterCourses as $semesterNumber => $courses) {
                foreach ($courses as $course) {
                    [$code, $name, $credits] = $course;
                    $type = $course[3] ?? CourseType::Theory;

                    Course::firstOrCreate(
                        ['code' => $code, 'version' => null],
                        [
                            'department_id' => $departmentId,
                            'semester_number' => $semesterNumber,
                            'code' => $code,
                            'name' => $name,
                            'credit_hours' => $credits,
                            'type' => $type,
                            'weekly_classes' => null,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
