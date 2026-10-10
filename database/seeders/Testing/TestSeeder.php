<?php

namespace Database\Seeders\Testing;

use App\Enums\CourseType;
use App\Enums\DesignationType;
use App\Enums\RoleKey;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Enrollment;
use App\Models\Hall;
use App\Models\InstitutionSetting;
use App\Models\Program;
use App\Models\Result;
use App\Models\RoleScope;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentProfile;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Results\GradingScaleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Deterministic dataset for automated tests (`php artisan seed:test`), spec §10.1.
 *
 * Phase 1 skeleton: institution, RBAC catalog + default matrix, two
 * departments/programs, two halls, academic terms, courses, one account per
 * role (two department heads, two provosts) with scopes, and five students in
 * the named states. Later phases extend {@see self::run()} with profiles,
 * results (incl. a retake), hall residencies, a library loan, signature
 * images, a 2FA user with a fixed test-only TOTP secret and an MCP integration.
 *
 * Idempotent: rows are matched on natural keys, so re-running is safe.
 * Identifiers are in {@see TestDataset}.
 */
class TestSeeder extends Seeder
{
    /** @var array<string, Department> */
    protected array $departments = [];

    /** @var array<string, Hall> */
    protected array $halls = [];

    /** @var array<string, Program> */
    protected array $programs = [];

    /** @var array<string, Batch> */
    protected array $batches = [];

    public function run(): void
    {
        app(AuditLogger::class)->withoutAuditing(function (): void {
            $this->call([PermissionSeeder::class, RoleSeeder::class, \Database\Seeders\GradingScaleSeeder::class, \Database\Seeders\ProfileRequiredFieldSeeder::class]);

            InstitutionSetting::query()->updateOrCreate([], [
                'institution_name' => 'FEC Test Institute',
                'short_name' => 'FEC',
                'principal_name' => 'Prof. Test Principal',
                'principal_title' => 'Principal',
            ]);

            $this->seedAcademicStructure();
            $this->seedStaffAccounts();
            $this->seedStudents();
            $this->seedProfiles();
            $this->seedAcademicRecords();
        });
    }

    protected function seedAcademicStructure(): void
    {
        foreach ([TestDataset::DEPT_CSE => 'Computer Science and Engineering', TestDataset::DEPT_EEE => 'Electrical and Electronic Engineering'] as $code => $name) {
            $this->departments[$code] = Department::query()->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);

            $this->programs[$code] = Program::query()->updateOrCreate(['code' => "BSC-{$code}"], [
                'department_id' => $this->departments[$code]->id,
                'name' => "B.Sc. in {$name}",
                'required_credits' => 12,
                'total_semesters' => 8,
                'is_active' => true,
            ]);

            $this->batches[$code] = Batch::query()->updateOrCreate(
                ['department_id' => $this->departments[$code]->id, 'batch_number' => 1],
                ['session' => '2021-2022', 'current_semester' => 8, 'is_active' => true],
            );
        }

        foreach ([TestDataset::HALL_A => ['Bijoy Ekattor Hall', 'male'], TestDataset::HALL_B => ['Sufia Kamal Hall', 'female']] as $code => [$name, $gender]) {
            $this->halls[$code] = Hall::query()->updateOrCreate(['code' => $code], ['name' => $name, 'gender' => $gender, 'capacity' => 200, 'is_active' => true]);
        }

        $terms = [
            ['SP2024', 'Spring 2024', '2024-01-01', '2024-06-30'],
            ['FA2024', 'Fall 2024', '2024-07-01', '2024-12-31'],
            ['SP2025', 'Spring 2025', '2025-01-01', '2025-06-30'],
            ['FA2025', 'Fall 2025', '2025-07-01', '2025-12-31'],
        ];

        foreach ($terms as [$code, $name, $startsOn, $endsOn]) {
            Semester::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_active' => $code === 'FA2025',
            ]);
        }

        $courses = [
            [TestDataset::DEPT_CSE, 'CSE-1101', 'Structured Programming', 1, CourseType::Theory, 3.0],
            [TestDataset::DEPT_CSE, 'CSE-1102', 'Structured Programming Lab', 1, CourseType::Lab, 1.5],
            [TestDataset::DEPT_CSE, 'CSE-1201', 'Data Structures', 2, CourseType::Theory, 3.0],
            [TestDataset::DEPT_CSE, 'CSE-1202', 'Discrete Mathematics', 2, CourseType::Theory, 3.0],
            [TestDataset::DEPT_CSE, 'CSE-1203', 'Data Structures Lab', 2, CourseType::Lab, 1.5],
            [TestDataset::DEPT_EEE, 'EEE-1101', 'Electrical Circuits I', 1, CourseType::Theory, 3.0],
            [TestDataset::DEPT_EEE, 'EEE-1102', 'Electrical Circuits Lab', 1, CourseType::Lab, 1.5],
            [TestDataset::DEPT_EEE, 'EEE-1201', 'Electronics I', 2, CourseType::Theory, 3.0],
            [TestDataset::DEPT_EEE, 'EEE-1202', 'Engineering Mathematics', 2, CourseType::Theory, 4.5],
        ];

        foreach ($courses as [$department, $code, $name, $semester, $type, $credits]) {
            Course::query()->updateOrCreate(['code' => $code, 'version' => null], [
                'department_id' => $this->departments[$department]->id,
                'name' => $name,
                'semester_number' => $semester,
                'type' => $type,
                'credit_hours' => $credits,
                'is_active' => true,
            ]);
        }
    }

    protected function seedStaffAccounts(): void
    {
        $this->account(TestDataset::SUPER_ADMIN, 'Sadia Rahman', [RoleKey::SuperAdmin]);
        $this->account(TestDataset::ADMIN_OFFICE, 'Kamal Hossain', [RoleKey::AdminOffice]);
        $this->account(TestDataset::HEAD_OF_INSTITUTION, 'Prof. Anwar Hossain', [RoleKey::HeadOfInstitution]);
        $this->account(TestDataset::PRINCIPAL, 'Prof. Test Principal', [RoleKey::Principal]);
        $this->account(TestDataset::LIBRARIAN, 'Nasrin Akter', [RoleKey::Librarian]);

        $this->account(TestDataset::PROVOST_A, 'Dr. Mahmudul Hasan', [RoleKey::HallProvost], [
            RoleKey::HallProvost->value => [$this->halls[TestDataset::HALL_A]->id],
        ]);
        $this->account(TestDataset::PROVOST_B, 'Dr. Farhana Islam', [RoleKey::HallProvost], [
            RoleKey::HallProvost->value => [$this->halls[TestDataset::HALL_B]->id],
        ]);

        $headCse = $this->account(TestDataset::DEPT_HEAD_CSE, 'Dr. Rafiqul Islam', [RoleKey::DepartmentHead, RoleKey::Teacher], [
            RoleKey::DepartmentHead->value => [$this->departments[TestDataset::DEPT_CSE]->id],
        ]);
        $headEee = $this->account(TestDataset::DEPT_HEAD_EEE, 'Dr. Shamima Nasrin', [RoleKey::DepartmentHead, RoleKey::Teacher], [
            RoleKey::DepartmentHead->value => [$this->departments[TestDataset::DEPT_EEE]->id],
        ]);
        $teacher = $this->account(TestDataset::TEACHER, 'Tanvir Ahmed', [RoleKey::Teacher]);

        $professor = Designation::query()->firstOrCreate(['name' => 'Professor'], ['short_name' => 'Prof.', 'type' => DesignationType::Teacher, 'is_active' => true]);
        $lecturer = Designation::query()->firstOrCreate(['name' => 'Lecturer'], ['short_name' => 'Lec.', 'type' => DesignationType::Teacher, 'is_active' => true]);

        $teacherProfiles = [
            [$headCse, TestDataset::DEPT_CSE, $professor, 'T-CSE-001', 'RI', ['CSE-1201']],
            [$headEee, TestDataset::DEPT_EEE, $professor, 'T-EEE-001', 'SN', ['EEE-1201']],
            [$teacher, TestDataset::DEPT_CSE, $lecturer, 'T-CSE-002', 'TA', ['CSE-1101', 'CSE-1102']],
        ];

        foreach ($teacherProfiles as [$user, $department, $designation, $employeeId, $shortName, $courseCodes]) {
            $profile = Teacher::query()->updateOrCreate(['employee_id' => $employeeId], [
                'user_id' => $user->id,
                'department_id' => $this->departments[$department]->id,
                'designation_id' => $designation->id,
                'short_name' => $shortName,
            ]);

            $profile->courses()->sync(Course::query()->whereIn('code', $courseCodes)->pluck('id'));
        }
    }

    protected function seedStudents(): void
    {
        $students = [
            [TestDataset::STUDENT_INCOMPLETE, 'Arif Chowdhury', TestDataset::DEPT_CSE, 'CSE-21-001'],
            [TestDataset::STUDENT_UNFINISHED, 'Mitu Begum', TestDataset::DEPT_CSE, 'CSE-21-002'],
            [TestDataset::STUDENT_ELIGIBLE, 'Rakib Hasan', TestDataset::DEPT_CSE, 'CSE-21-003'],
            [TestDataset::STUDENT_NON_RESIDENT, 'Nusrat Jahan', TestDataset::DEPT_CSE, 'CSE-21-004'],
            [TestDataset::STUDENT_LIBRARY_LOAN, 'Tasnim Sultana', TestDataset::DEPT_EEE, 'EEE-21-001'],
        ];

        foreach ($students as [$email, $name, $department, $roll]) {
            $user = $this->account($email, $name, [RoleKey::Student]);

            Student::query()->updateOrCreate(['roll_number' => $roll], [
                'user_id' => $user->id,
                'department_id' => $this->departments[$department]->id,
                'program_id' => $this->programs[$department]->id,
                'batch_id' => $this->batches[$department]->id,
                'registration_number' => 'REG-'.$roll,
                'current_semester' => 8,
            ]);
        }
    }

    /**
     * Complete profiles for every student except the "incomplete" one.
     */
    protected function seedProfiles(): void
    {
        $residency = [
            TestDataset::STUDENT_ELIGIBLE => TestDataset::HALL_A,
            TestDataset::STUDENT_LIBRARY_LOAN => TestDataset::HALL_B,
            TestDataset::STUDENT_UNFINISHED => TestDataset::HALL_A,
            TestDataset::STUDENT_NON_RESIDENT => null,
        ];

        $index = 0;

        foreach ($residency as $email => $hall) {
            $student = Student::query()->where('user_id', User::query()->where('email', $email)->value('id'))->firstOrFail();
            $index++;

            $student->update(['phone' => '017000000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);

            StudentProfile::query()->updateOrCreate(['student_id' => $student->id], [
                'full_name_certificate' => $student->user->name,
                'father_name' => 'Abdul Karim',
                'mother_name' => 'Rahima Khatun',
                'date_of_birth' => '2001-04-12',
                'email' => $student->user->email,
                'present_address' => 'House 12, Road 3, Dhaka',
                'permanent_address' => 'Village Rampur, Bogura',
                'photo_path' => 'student-photos/test-photo.png',
                'guardian_name' => 'Abdul Karim',
                'guardian_phone' => '01800000000',
                'blood_group' => 'B+',
                'nid_or_birth_reg' => '19990123456789012',
                'is_residential' => $hall !== null,
                'hall_id' => $hall === null ? null : $this->halls[$hall]->id,
                'emergency_contact_name' => 'Rahima Khatun',
                'emergency_contact_phone' => '01900000000',
                'profile_completed_at' => now(),
                'locked_fields' => null,
            ]);
        }
    }

    /**
     * Offerings, enrollments and results with hand-calculated GPAs, see
     * {@see TestDataset::EXPECTED_RESULTS}. The eligible student has a failed
     * course that is retaken, plus an unpublished improvement attempt.
     */
    protected function seedAcademicRecords(): void
    {
        $scale = app(GradingScaleService::class);

        $plan = [
            TestDataset::STUDENT_ELIGIBLE => [
                ['SP2024', 'CSE-1101', 82, 'regular', 'published'],
                ['SP2024', 'CSE-1102', 72, 'regular', 'published'],
                ['FA2024', 'CSE-1201', 68, 'regular', 'published'],
                ['FA2024', 'CSE-1202', 35, 'regular', 'published'],
                ['SP2025', 'CSE-1202', 66, 'retake', 'published'],
                ['SP2025', 'CSE-1203', 90, 'regular', 'published'],
                ['FA2025', 'CSE-1201', 85, 'improvement', 'approved'],
            ],
            TestDataset::STUDENT_UNFINISHED => [
                ['SP2024', 'CSE-1101', 72, 'regular', 'published'],
                ['SP2024', 'CSE-1102', 60, 'regular', 'published'],
                ['FA2025', 'CSE-1201', 77, 'regular', 'submitted'],
            ],
            TestDataset::STUDENT_NON_RESIDENT => [
                ['SP2024', 'CSE-1101', 80, 'regular', 'published'],
                ['SP2024', 'CSE-1102', 76, 'regular', 'published'],
                ['FA2024', 'CSE-1201', 70, 'regular', 'published'],
                ['FA2024', 'CSE-1202', 66, 'regular', 'published'],
                ['SP2025', 'CSE-1203', 60, 'regular', 'published'],
            ],
            TestDataset::STUDENT_LIBRARY_LOAN => [
                ['SP2024', 'EEE-1101', 90, 'regular', 'published'],
                ['SP2024', 'EEE-1102', 85, 'regular', 'published'],
                ['FA2024', 'EEE-1201', 72, 'regular', 'published'],
                ['FA2024', 'EEE-1202', 66, 'regular', 'published'],
            ],
        ];

        foreach ($plan as $email => $rows) {
            $student = Student::query()->where('user_id', User::query()->where('email', $email)->value('id'))->firstOrFail();

            foreach ($rows as [$semesterCode, $courseCode, $marks, $attempt, $status]) {
                $course = Course::query()->where('code', $courseCode)->firstOrFail();
                $semester = Semester::query()->where('code', $semesterCode)->firstOrFail();

                $offering = CourseOffering::query()->firstOrCreate(
                    ['course_id' => $course->id, 'semester_id' => $semester->id, 'section' => 'A'],
                    ['teacher_id' => $course->teachers()->value('teachers.id')],
                );

                $enrollment = Enrollment::query()->updateOrCreate(
                    ['student_id' => $student->id, 'course_offering_id' => $offering->id],
                    ['attempt_type' => $attempt, 'status' => 'enrolled'],
                );

                $grade = $scale->gradeFor((float) $marks);

                Result::query()->updateOrCreate(['enrollment_id' => $enrollment->id], [
                    'marks' => $marks,
                    'letter' => $grade['letter'],
                    'grade_point' => $grade['point'],
                    'status' => $status,
                    'submitted_at' => now(),
                    'approved_at' => in_array($status, ['approved', 'published'], true) ? now() : null,
                    'published_at' => $status === 'published' ? now() : null,
                ]);
            }
        }
    }

    /**
     * @param  list<RoleKey>  $roles
     * @param  array<string, list<int>>  $scopes  role key → scope ids
     */
    protected function account(string $email, string $name, array $roles, array $scopes = []): User
    {
        $user = User::query()->withTrashed()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(TestDataset::PASSWORD),
            'email_verified_at' => now(),
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $user->syncRoles(array_map(fn (RoleKey $role): Role => Role::findByName($role->value, 'web'), $roles));

        RoleScope::query()->where('user_id', $user->id)->delete();

        foreach ($scopes as $roleKey => $ids) {
            $role = Role::findByName($roleKey, 'web');
            $type = RoleKey::from($roleKey)->defaultScope();

            foreach ($ids as $id) {
                RoleScope::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'scope_type' => $type->value,
                    'scope_id' => $id,
                ]);
            }
        }

        return $user;
    }
}
