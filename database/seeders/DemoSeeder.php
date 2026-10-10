<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\ClearanceRequest;
use App\Models\Hall;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\InstitutionSetting;
use App\Models\LibraryLoan;
use App\Models\Notice;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Clearance\ClearanceService;
use Database\Seeders\Testing\TestDataset;
use Database\Seeders\Testing\TestSeeder;

/**
 * Demo dataset (`php artisan seed:demo`): the deterministic role/academic
 * skeleton plus ~35 more students with profiles and results, hall residents,
 * library loans, notices and clearance requests in every state, so the app
 * looks alive on first open. Never contains the test-only 2FA user, TOTP
 * secret or MCP token (spec §10.1). Every demo account uses the password
 * "password" — local demos only.
 */
class DemoSeeder extends TestSeeder
{
    /** @var list<array{0: string, 1: string}> */
    private const NAMES = [
        ['Farhan', 'Tanvir'], ['Sadia', 'Afrin'], ['Imtiaz', 'Karim'], ['Nabila', 'Rahman'], ['Shakib', 'Hossain'], ['Mahira', 'Khan'],
        ['Tawsif', 'Ahmed'], ['Lamia', 'Sultana'], ['Rafiul', 'Islam'], ['Anika', 'Tabassum'], ['Zahid', 'Hasan'], ['Mim', 'Chowdhury'],
        ['Asif', 'Mahmud'], ['Ritu', 'Akter'], ['Naim', 'Uddin'], ['Sumi', 'Khatun'], ['Arman', 'Sheikh'], ['Tania', 'Parvin'],
        ['Sourav', 'Das'], ['Priya', 'Roy'], ['Emon', 'Mia'], ['Jui', 'Begum'], ['Hridoy', 'Sarker'], ['Nusaiba', 'Haque'],
        ['Kabir', 'Ali'], ['Fariha', 'Noor'], ['Mehedi', 'Hasan'], ['Shormi', 'Das'], ['Raihan', 'Talukder'], ['Tuba', 'Zaman'],
        ['Omar', 'Faruk'], ['Samira', 'Binte'], ['Jubayer', 'Alam'], ['Rubaiya', 'Sultana'], ['Tamim', 'Iqbal'],
    ];

    /** @var array<string, Student> */
    protected array $demoStudents = [];

    public function run(): void
    {
        // Seeded students are fixtures, not people to look up on the exam portal.
        config(['result_portal.enabled' => false, 'notifications.enabled' => false]);

        app(AuditLogger::class)->withoutAuditing(function (): void {
            $this->seedBase();

            InstitutionSetting::query()->updateOrCreate([], [
                'institution_name' => 'FEC Engineering College',
                'short_name' => 'FEC',
                'address' => 'Feni, Chattogram Division, Bangladesh',
                'principal_name' => 'Prof. Dr. Anwarul Haque',
                'principal_title' => 'Principal',
            ]);

            $this->seedDemoStudents();
            $this->seedDemoCampus();
            $this->seedNotices();
        });

        $this->seedClearanceStories();
    }

    protected function seedDemoStudents(): void
    {
        $courses = ['CSE-1101', 'CSE-1102', 'CSE-1201', 'CSE-1202', 'CSE-1203'];
        $eeeCourses = ['EEE-1101', 'EEE-1102', 'EEE-1201', 'EEE-1202'];
        $marks = [88, 76, 71, 66, 91, 59, 83, 68, 77, 62, 94, 73];
        $halls = [TestDataset::HALL_A, TestDataset::HALL_B, null];

        foreach (self::NAMES as $index => [$first, $last]) {
            $department = $index % 3 === 2 ? TestDataset::DEPT_EEE : TestDataset::DEPT_CSE;
            $roll = ($department === TestDataset::DEPT_CSE ? 'CSE' : 'EEE').'-21-'.str_pad((string) (10 + $index), 3, '0', STR_PAD_LEFT);
            $email = strtolower("{$first}.{$last}").'@fec.test';

            $user = $this->account($email, "{$first} {$last}", [RoleKey::Student]);

            $student = Student::query()->updateOrCreate(['roll_number' => $roll], [
                'user_id' => $user->id,
                'department_id' => $this->departments[$department]->id,
                'program_id' => $this->programs[$department]->id,
                'batch_id' => $this->batches[$department]->id,
                'registration_number' => 'REG-'.$roll,
                'current_semester' => 8,
            ]);

            $this->demoStudents[$email] = $student;
            $this->completeProfile($student, '01710'.str_pad((string) (200000 + $index), 6, '0', STR_PAD_LEFT), $halls[$index % 3]);

            $codes = $department === TestDataset::DEPT_CSE ? $courses : $eeeCourses;
            $semesters = ['SP2024', 'SP2024', 'FA2024', 'FA2024', 'SP2025'];
            $rows = [];

            foreach ($codes as $position => $code) {
                $rows[] = [$semesters[$position], $code, $marks[($index + $position) % count($marks)], 'regular', 'published'];
            }

            if ($index % 7 === 6) {
                $rows = array_slice($rows, 0, 3);
            }

            $this->recordResults($student, $rows);
        }
    }

    protected function seedDemoCampus(): void
    {
        $halls = ['A' => TestDataset::HALL_A, 'B' => TestDataset::HALL_B];

        foreach ($this->demoStudents as $email => $student) {
            $profile = $student->profile()->first();

            if ($profile?->is_residential && $profile->hall_id !== null) {
                HallResidency::query()->updateOrCreate(
                    ['student_id' => $student->id, 'ended_on' => null],
                    ['hall_id' => $profile->hall_id, 'room' => (string) (100 + ($student->id % 40)), 'assigned_on' => '2021-08-01'],
                );
            }
        }

        $titles = ['Operating Systems Concepts', 'Digital Logic Design', 'Engineering Mathematics III', 'Signals and Systems', 'Database System Concepts'];

        foreach (array_slice(array_values($this->demoStudents), 3, 5) as $position => $student) {
            LibraryLoan::query()->updateOrCreate(
                ['student_id' => $student->id, 'accession_no' => 'ACC-D'.($position + 100)],
                ['book_title' => $titles[$position], 'issued_on' => now()->subDays(40), 'due_on' => now()->subDays(26), 'returned_on' => $position % 2 === 0 ? now()->subDays(20) : null, 'fine_amount' => $position % 2 === 0 ? 70 : 0],
            );
        }

        $resident = HallResidency::query()->current()->with('student')->first();

        if ($resident !== null) {
            HallDue::query()->updateOrCreate(
                ['student_id' => $resident->student_id, 'description' => 'Electricity charge, last semester'],
                ['hall_id' => $resident->hall_id, 'amount' => 850, 'settled_at' => null],
            );
        }
    }

    protected function seedNotices(): void
    {
        $author = User::query()->where('email', TestDataset::SUPER_ADMIN)->firstOrFail();
        $cse = $this->departments[TestDataset::DEPT_CSE];
        $hall = $this->halls[TestDataset::HALL_A];

        $notices = [
            ['Final exam routine published', 'The final examination routine for Fall 2025 is available from the Academic Section.', 'all', null, null],
            ['Library closed on Friday', 'The central library will remain closed this Friday for stock verification.', 'all', null, null],
            ['CSE lab orientation', 'CSE students: the Data Structures Lab orientation is on Sunday at 10:00 in Lab 2.', 'department', $cse->id, null],
            ['Hall dues reminder', 'Residents of Bijoy Ekattor Hall are asked to clear pending dues before applying for clearance.', 'hall', null, $hall->id],
        ];

        foreach ($notices as [$title, $body, $audience, $departmentId, $hallId]) {
            Notice::query()->updateOrCreate(['title' => $title], [
                'body' => $body, 'audience' => $audience, 'department_id' => $departmentId, 'hall_id' => $hallId,
                'published_at' => now()->subDays(2), 'created_by' => $author->id,
            ]);
        }
    }

    /**
     * Clearance requests in every state, created through the real service so
     * the timeline, signatures and hash chain are genuine.
     */
    protected function seedClearanceStories(): void
    {
        if (ClearanceRequest::query()->exists()) {
            return;
        }

        $service = app(ClearanceService::class);
        $emails = array_keys($this->demoStudents);
        $approvers = fn (string $stage): User => User::query()->where('email', match ($stage) {
            'hall_a' => TestDataset::PROVOST_A,
            'hall_b' => TestDataset::PROVOST_B,
            'library' => TestDataset::LIBRARIAN,
            'cse' => TestDataset::DEPT_HEAD_CSE,
            'eee' => TestDataset::DEPT_HEAD_EEE,
            'head' => TestDataset::HEAD_OF_INSTITUTION,
        })->firstOrFail();
        $office = User::query()->where('email', TestDataset::ADMIN_OFFICE)->firstOrFail();

        $eligible = collect($emails)->filter(function (string $email) use ($service): bool {
            $student = $this->demoStudents[$email];

            return $student->department_id === $this->departments[TestDataset::DEPT_CSE]->id
                && $service->checkEligibility(User::query()->where('email', $email)->firstOrFail())->eligible();
        })->values();

        $walk = function (string $email, array $chain) use ($service, $approvers): ClearanceRequest {
            $request = $service->apply(User::query()->where('email', $email)->firstOrFail());

            foreach ($chain as $stage) {
                $request = $service->approve($approvers($stage), $request->id, 'Checked and cleared.');
            }

            return $request;
        };

        $residential = fn (string $email): bool => (bool) $this->demoStudents[$email]->profile()->value('is_residential');
        $hallStage = fn (string $email): array => $residential($email) ? [($this->demoStudents[$email]->profile()->value('hall_id') === $this->halls[TestDataset::HALL_A]->id ? 'hall_a' : 'hall_b')] : [];

        $pick = $eligible->all();

        if (count($pick) < 5) {
            return;
        }

        [$collected, $ready, $rejected, $atHead, $atHall] = $pick;

        $request = $walk($collected, [...$hallStage($collected), 'library', 'cse', 'head']);
        $service->recordPrint($office, $request->id);
        $service->markCollected($office, $request->id, true);

        $walk($ready, [...$hallStage($ready), 'library', 'cse', 'head']);

        $request = $walk($rejected, $hallStage($rejected));
        $service->reject($approvers('library'), $request->id, 'Return 2 library books and clear the fine.');

        $walk($atHead, [...$hallStage($atHead), 'library', 'cse']);
        $service->apply(User::query()->where('email', $atHall)->firstOrFail());
    }
}
