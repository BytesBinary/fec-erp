<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    /**
     * Courses grouped by department code → semester number → [code, name, credits, type?, version?].
     *
     * - type    : defaults to Theory when omitted
     * - version : null = latest curriculum, 1 = previous/old curriculum
     *
     * @var array<string, array<int, array<int, array{0: string, 1: string, 2: float, 3?: CourseType, 4?: int}>>>
     */
    private array $courses = [

        // ─────────────────────────────── CSE ───────────────────────────────
        'CSE' => [
            1 => [
                ['CSE-1101', 'Fundamentals of Computers and Computing',              2.0],
                ['CSE-1102', 'Discrete Mathematics',                                 3.0],
                ['EEE-1103', 'Electrical Circuits',                                  3.0],
                ['CHE-1104', 'Chemistry',                                             3.0],
                ['MATH-1105', 'Differential and Integral Calculus',                  3.0],
                ['SS-1106',  'Government and Public Administration',                 2.0],
                ['CSE-1111', 'Fundamentals of Computers and Computing Lab',          1.5, CourseType::Lab],
                ['EEE-1113', 'Electrical Circuits Lab',                              1.5, CourseType::Lab],
                ['CHE-1114', 'Chemistry Lab',                                         1.5, CourseType::Lab],
            ],
            2 => [
                ['CSE-1201', 'Fundamentals of Programming',                          3.0],
                ['CSE-1202', 'Digital Logic Design',                                 3.0],
                ['PHY-1203', 'Physics',                                              3.0],
                ['MATH-1204', 'Methods of Integration, Differential Equations and Series', 3.0],
                ['ENG-1205', 'Developing English Language Skills',                   2.0],
                ['CSE-1211', 'Fundamentals of Programming Lab',                      3.0, CourseType::Lab],
                ['CSE-1212', 'Digital Logic Design Lab',                             1.5, CourseType::Lab],
                ['PHY-1213', 'Physics Lab',                                          1.5, CourseType::Lab],
                ['ENG-1215', 'Developing English Language Skills Lab',               1.5, CourseType::Lab],
            ],
            3 => [
                ['CSE-2101', 'Data Structures and Algorithms',                       3.0],
                ['CSE-2102', 'Object Oriented Programming',                          3.0],
                ['CSE-2103', 'Digital Electronics and Pulse Technique',              3.0],
                ['EEE-2104', 'Electronic Devices and Circuits',                      3.0],
                ['MATH-2105', 'Linear Algebra',                                      3.0],
                ['SS-2106',  'Bangladesh Studies',                                   2.0],
                ['CSE-2111', 'Data Structures and Algorithms Lab',                   1.5, CourseType::Lab],
                ['CSE-2112', 'Object Oriented Programming Lab',                      1.5, CourseType::Lab],
                ['CSE-2113', 'Digital Electronics and Pulse Technique Lab',          1.5, CourseType::Lab],
                ['EEE-2114', 'Electronic Devices and Circuits Lab',                  0.75, CourseType::Lab],
            ],
            4 => [
                ['CSE-2201', 'Database Management Systems-I',                        3.0],
                ['CSE-2202', 'Design and Analysis of Algorithms-I',                  3.0],
                ['CSE-2203', 'Data and Telecommunication',                           3.0],
                ['CSE-2204', 'Computer Architecture and Organization',               3.0],
                ['CSE-2205', 'Introduction to Mechatronics',                         2.0],
                ['CSE-2211', 'Database Management Systems - I Lab',                  1.5, CourseType::Lab],
                ['CSE-2212', 'Design and Analysis of Algorithms - I Lab',            1.5, CourseType::Lab],
                ['CSE-2213', 'Data and Telecommunication Lab',                       0.75, CourseType::Lab],
                ['CSE-2216', 'Application Development Lab',                          1.5, CourseType::Lab],
            ],
            5 => [
                ['CSE-3101', 'Computer Networking',                                  3.0],
                ['CSE-3102', 'Software Engineering',                                 3.0],
                ['CSE-3103', 'Microprocessor and Microcontroller',                   3.0],
                ['CSE-3104', 'Database Management Systems -II',                      3.0],
                ['MATH-3105', 'Multivariable Calculus and Geometry',                 3.0],
                ['CSE-3111', 'Computer Networking Lab',                              1.5, CourseType::Lab],
                ['CSE-3112', 'Software Engineering Lab',                             0.75, CourseType::Lab],
                ['CSE-3113', 'Microprocessor and Assembly Language Lab',             1.5, CourseType::Lab],
                ['CSE-3116', 'Microcontroller Lab',                                  0.75, CourseType::Lab],
            ],
            6 => [
                ['CSE-3201', 'Operating Systems',                                    3.0],
                ['CSE-3202', 'Numerical Methods',                                    3.0],
                ['CSE-3203', 'Design and Analysis of Algorithms - II',               3.0],
                ['CSE-3204', 'Formal Language, Automata and Computability',          3.0],
                ['STAT-3205', 'Introduction to Probability and Statistics',          3.0],
                ['CSE-3211', 'Operating Systems Lab',                                1.5, CourseType::Lab],
                ['CSE-3212', 'Numerical Methods Lab',                                0.75, CourseType::Lab],
                ['CSE-3216', 'Software Design Patterns Lab',                         1.5, CourseType::Lab],
                ['ENG-3217', 'Technical Writing and Presentation Lab',               0.75, CourseType::Lab],
            ],
            7 => [
                // Core
                ['CSE-4101', 'Artificial Intelligence',                              3.0],
                ['CSE-4102', 'Mathematical and Statistical Analysis for Engineers',  3.0],
                ['SS-4103',  'Entrepreneurship for IT Business',                     2.0],
                ['CSE-4111', 'Artificial Intelligence Lab',                          1.5, CourseType::Lab],
                ['CSE-4113', 'Internet Programming Lab',                             1.5, CourseType::Lab],
                ['CSE-4114', 'Project',                                              2.0, CourseType::Lab],
                // Electives – theory
                ['CSE-4121', 'Robotics Science and Systems',                         3.0],
                ['CSE-4122', 'Mathematics for Robotics',                             3.0],
                ['CSE-4123', 'Computational Methods in Bio-molecular Sequence & Structure Analysis', 3.0],
                ['CSE-4124', 'Introduction to Bioinformatics',                       3.0],
                ['CSE-4125', 'Introduction to Machine Learning',                     3.0],
                ['CSE-4126', 'Introduction to Data Science',                         3.0],
                ['CSE-4127', 'Information Retrieval',                                3.0],
                ['CSE-4128', 'Wireless Networks',                                    3.0],
                ['CSE-4130', 'Introduction to Quantum Logic',                        3.0],
                ['CSE-4131', 'Introduction to VLSI Design',                          3.0],
                ['CSE-4132', 'Graph Theory',                                         3.0],
                ['CSE-4133', 'Algorithm Engineering',                                3.0],
                ['CSE-4134', 'Software Project Management',                          3.0],
                ['CSE-4135', 'Software Requirements Specification and Analysis',     3.0],
                ['CSE-4136', 'Computer Security',                                    3.0],
                ['CSE-4137', 'Cryptography and Security',                            3.0],
                ['CSE-4139', 'Computer Graphics',                                    3.0],
                ['CSE-4140', 'Compiler Design',                                      3.0],
                // Electives – lab
                ['CSE-4151', 'Robotics Science and Systems Lab',                     1.5, CourseType::Lab],
                ['CSE-4153', 'Computational Methods in Bio-molecular Sequence & Structure Analysis Lab', 1.5, CourseType::Lab],
                ['CSE-4155', 'Introduction to Machine Learning Lab',                 1.5, CourseType::Lab],
                ['CSE-4157', 'Information Retrieval Lab',                            1.5, CourseType::Lab],
                ['CSE-4161', 'Introduction to VLSI Design Lab',                      1.5, CourseType::Lab],
                ['CSE-4163', 'Algorithm Engineering Lab',                            1.5, CourseType::Lab],
                ['CSE-4165', 'Software Requirements Specification and Analysis Lab', 1.5, CourseType::Lab],
                ['CSE-4167', 'Cryptography and Security Lab',                        1.5, CourseType::Lab],
                ['CSE-4169', 'Computer Graphics Lab',                                1.5, CourseType::Lab],
            ],
            8 => [
                // Core
                ['ECO-4201', 'Economics',                                            2.0],
                ['CSE-4202', 'Society and Technology',                               2.0],
                ['SS-4203',  'Engineering Ethics',                                   2.0],
                ['CSE-4214', 'Project',                                              1.5, CourseType::Lab],
                // Electives – theory
                ['CSE-4221', 'Robot Learning',                                       3.0],
                ['CSE-4222', 'Human Robot Interaction',                              3.0],
                ['CSE-4223', 'Fundamentals of Genomics and Proteomics',             3.0],
                ['CSE-4224', 'Mobile Robotics',                                      3.0],
                ['CSE-4225', 'Introduction to Data Mining and Warehousing',          3.0],
                ['CSE-4226', 'Aerial Robotics',                                      3.0],
                ['CSE-4227', 'Cloud Computing',                                      3.0],
                ['CSE-4228', 'Application of Computational Biology',                 3.0],
                ['CSE-4229', 'Introduction to Reversible Computing',                 3.0],
                ['CSE-4230', 'Human Computer Interaction',                           3.0],
                ['CSE-4231', 'Computational Geometry',                               3.0],
                ['CSE-4232', 'Internet of Things',                                   3.0],
                ['CSE-4233', 'Software Testing and Verification',                    3.0],
                ['CSE-4234', 'Introduction to Multiple-Valued Logic',                3.0],
                ['CSE-4235', 'Digital Forensic',                                     3.0],
                ['CSE-4236', 'VLSI Layout Algorithms',                               3.0],
                ['CSE-4237', 'Digital Image Processing',                             3.0],
                ['CSE-4238', 'Concepts of Concurrent Computation',                   3.0],
                ['CSE-4239', 'Parallel and Distributed Systems',                     3.0],
                ['CSE-4240', 'Applied Cryptography',                                 3.0],
                ['CSE-4242', 'Computer Vision',                                      3.0],
                ['CSE-4244', 'Computer and Network Security',                        3.0],
                ['CSE-4246', 'Natural Language Processing',                          3.0],
                // Electives – lab
                ['CSE-4251', 'Robot Learning Lab',                                   1.5, CourseType::Lab],
                ['CSE-4253', 'Fundamentals of Genomics and Proteomics Lab',          1.5, CourseType::Lab],
                ['CSE-4255', 'Introduction to Data Mining and Warehousing Lab',      1.5, CourseType::Lab],
                ['CSE-4257', 'Cloud Computing Lab',                                  1.5, CourseType::Lab],
                ['CSE-4259', 'Introduction to Reversible Computing Lab',             1.5, CourseType::Lab],
                ['CSE-4261', 'Computational Geometry Lab',                           1.5, CourseType::Lab],
                ['CSE-4263', 'Software Testing and Verification Lab',                1.5, CourseType::Lab],
                ['CSE-4265', 'Digital Forensic Lab',                                 1.5, CourseType::Lab],
                ['CSE-4267', 'Digital Image Processing Lab',                         1.5, CourseType::Lab],
                ['CSE-4269', 'Parallel and Distributed Systems Lab',                 1.5, CourseType::Lab],
            ],
        ],

        // ─────────────────────────────── EEE ───────────────────────────────
        'EEE' => [
            1 => [
                ['EEE-1101', 'Electrical Circuit-I',                                 3.0],
                ['MATH-1101', 'Differential & Integral Calculus and Co-ordinate Geometry', 3.0],
                ['CSE-1101', 'Computer Programming',                                 3.0],
                ['PHY-1101', 'Electricity and Magnetism, Modern Physics and Mechanics', 3.0],
                ['GED-1101', 'English for Technical Communication',                  3.0],
            ],
            2 => [
                ['EEE-1201', 'Electrical Circuit II',                                3.0],
                ['EEE-1203', 'Electrical Properties Of Materials',                   3.0],
                ['PHY-1201', 'Waves and Oscillations, Optics and Thermal Physics',   3.0],
                ['MATH-1201', 'Differential Equations and Complex Variables',         3.0],
                ['CHEM-1201', 'Chemistry',                                            3.0],
                ['GED-1201', 'Bangladesh Studies',                                   3.0],
            ],
            5 => [
                ['EEE-3101', 'Electrical Measurement & Instrumentation',             3.0],
                ['EEE-3103', 'Digital Electronics',                                  3.0],
                ['EEE-3105', 'Power System I',                                       3.0],
                ['EEE-3107', 'Power Electronics and Industrial Drives',              3.0],
                ['GED-3101', 'Engineering Management',                               3.0],
            ],
            6 => [
                // New curriculum
                ['EEE-3201', 'Communication Engineering Fundamentals',               3.0],
                ['EEE-3203', 'Power System-II',                                      3.0],
                ['EEE-3205', 'Signal and Systems',                                   3.0],
                ['EEE-3207', 'Numerical Methods',                                    3.0],
                // Old curriculum
                ['EEE-601',  'Communication Theory (old)',                            3.0],
                ['EEE-603',  'Digital Signal Processing (old)',                       3.0],
                ['EEE-605',  'Microprocessor and Interfacing (old)',                  3.0],
                ['EEE-607',  'Power System-II (old)',                                 3.0],
                ['EEE-609',  'Analog Integrated Circuits (old)',                      3.0],
                // Same-code alternates (version 1)
                ['EEE-3201', 'Microprocessor and Microcontroller',                   3.0, CourseType::Theory, 1],
                ['EEE-601',  'Industrial Management (old)',                           3.0, CourseType::Theory, 1],
            ],
        ],

        // ─────────────────────────────── CE ────────────────────────────────
        'CE' => [
            1 => [
                ['HUM-101',  'English',                                              2.0],
                ['CE-102',   'Engineering Mechanics',                                4.0],
                ['MATH-101', 'Differential and Integral Calculus',                   3.0],
                ['CHEM-101', 'Chemistry-I',                                           3.0],
                ['PHY-101',  'Physics Optics, Heat, Waves and Oscillation',          3.0],
            ],
            2 => [
                ['CE-202',   'Surveying',                                             4.0],
                ['CHEM-201', 'Chemistry-II',                                          3.0],
                ['MATH-201', 'Differential Equation And Statistics',                 3.0],
                ['HUM-201',  'Sociology And Government',                             4.0],
                ['PHY-201',  'Structure Of Matter, Electricity and Magnetism and Modern Physics', 3.0],
            ],
            5 => [
                ['CE-501',   'Structural Analysis and Design-I',                     3.0],
                ['CE-503',   'Design of Concrete Structure-I',                        3.0],
                ['CE-504',   'Environmental Engineering-I',                           3.0],
                ['CE-505',   'Principles of Soil Mechanics',                          3.0],
                ['CE-507',   'Open Channel Flow',                                    3.0],
            ],
            6 => [
                ['CE-601',   'Structural Analysis and Design-II (old+new)',           3.0],
                ['CE-602',   'Design of Concrete Structures-II (old+new)',            4.0],
                ['CE-604',   'Principles of Foundation Engineering (new)',            3.0],
                ['CE-604',   'Geotechnical Engineering-II (old)',                     3.0, CourseType::Theory, 1],
                ['CE-605',   'Transportation Engineering-I: Transport & Traffic Design (old+new)', 3.0],
                ['CE-607',   'Hydrology (old+new)',                                  3.0],
            ],
            7 => [
                ['CE-701',   'Environmental Engineering II (Old + New)',              3.0],
                ['CE-702',   'Transportation Engineering II: Highway Design And Railways', 3.0],
                ['CE-703',   'Project Planning And Management',                       3.0],
                ['CE-704',   'Structural Analysis And Design III (Old+New)',          3.0],
                ['CE-705',   'Integration And Flood Control (Old)',                   3.0],
                ['CE-706',   'Integration And Flood Control (New)',                   3.0],
            ],
            8 => [
                ['CE-801',   'Professional Practice and Communication (all)',         2.0],
                ['CE-802',   'Professional Practices and Communication (FEC,BEC old improvement)', 2.0],
                ['CE-803',   'Socio Economic Aspects of Development Project (MEC Old improvement)', 2.0],
                ['CE-804',   'Prestressed Concrete (MEC+BEC)',                       2.0],
                ['CE-805',   'Design of Steel Structures (MEC,BEC)',                 2.0],
                ['CE-805',   'Prestressed Concrete (MEC+BEC old improvement)',       2.0, CourseType::Theory, 1],
                ['CE-806',   'Design of Steel Structures (MEC,BEC old improvement)', 2.0],
                ['CE-809',   'Solid Hazardous Waste Management (all)',               2.0],
                ['CE-810',   'Environmental Pollution Management (all)',              2.0],
                ['CE-810',   'Environmental Engineering-III (all old improvement)',   2.0, CourseType::Theory, 1],
                ['CE-811',   'Environmental Engineering-IV (all old improvement)',    2.0],
                ['CE-817',   'Transportation Engineering-III (FEC)',                  2.0],
                ['CE-818',   'Transportation Engineering-IV (FEC)',                   2.0],
                ['CE-819',   'Transportation Engineering-III (FEC+BEC old improvement)', 2.0],
                ['CE-820',   'Transportation Engineering-IV (FEC+BEC old improvement)', 2.0],
            ],
        ],
    ];

    public function run(): void
    {
        // Replace placeholder course data with real curriculum data.
        // FK cascades handle routine_slots; disable checks to allow truncation order.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('course_teacher')->truncate();
        DB::table('routine_slots')->truncate();
        DB::table('courses')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $departments = Department::pluck('id', 'code');

        foreach ($this->courses as $deptCode => $semesterCourses) {
            $departmentId = $departments[$deptCode] ?? null;

            if ($departmentId === null) {
                continue;
            }

            foreach ($semesterCourses as $semesterNumber => $courses) {
                foreach ($courses as $course) {
                    $code = $course[0];
                    $name = $course[1];
                    $credits = $course[2] ?? 3.0;
                    $type = $course[3] ?? CourseType::Theory;
                    $version = $course[4] ?? null;

                    Course::updateOrCreate(
                        [
                            'code' => strtoupper($code),
                            'department_id' => $departmentId,
                            'version' => $version,
                        ],
                        [
                            'semester_number' => $semesterNumber,
                            'name' => $name,
                            'credit_hours' => $credits,
                            'type' => $type,
                            'weekly_classes' => $type === CourseType::Lab ? 1 : null,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
