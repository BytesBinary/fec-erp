<?php

use App\Enums\PortalChangeType;
use App\Enums\PortalExamKind;
use App\Enums\ResultPullStatus;
use App\Exceptions\Domain\ResultPortalException;
use App\Filament\Resources\ResultPulls\Pages\ListResultPulls;
use App\Models\Batch;
use App\Models\Department;
use App\Models\PortalResult;
use App\Models\ResultPull;
use App\Models\Student;
use App\Services\People\StudentService;
use App\Services\ResultPortal\Contracts\ResultSource;
use App\Services\ResultPortal\ExamCatalog;
use App\Services\ResultPortal\ExamListing;
use App\Services\ResultPortal\PortalResultImporter;
use App\Services\ResultPortal\PortalResultRow;
use App\Services\ResultPortal\ResultPullService;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;
use Tests\Mcp\McpClient;
use Tests\Support\FakeResultSource;

beforeEach(function () {
    seedTestDataset();
    config(['result_portal.enabled' => true]);
    $this->source = new FakeResultSource;
    app()->instance(ResultSource::class, $this->source);
});

function portalRow(int $examId, string $course, string $letter, float $point, PortalExamKind $kind = PortalExamKind::Regular): PortalResultRow
{
    return new PortalResultRow(new ExamListing($examId, "Exam {$examId}", $kind, 5), $course, "Title of {$course}", 3.0, $letter, $point);
}

function newStudentPayload(array $overrides = []): array
{
    $department = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

    return [
        'name' => 'Newly Added', 'email' => 'new.student@fec.test', 'password' => 'password-123',
        'department_id' => $department->id, 'batch_id' => Batch::query()->first()->id,
        'roll_number' => 'R-9001', 'registration_number' => '2022954853', 'current_semester' => 5,
        ...$overrides,
    ];
}

describe('exam catalog', function () {
    it('reads kind and semester from the portal\'s free-text exam titles', function () {
        $catalog = app(ExamCatalog::class);

        expect($catalog->semesterOf('B.Sc. in Computer Science and Engineering 3rd year 2nd Semester Examination of 2024'))->toBe(6)
            ->and($catalog->semesterOf('B.Sc. in Civil Engineering 1st  year 2nd  Semester Special  Improvement Examination of 2023 (Special Improvement)'))->toBe(2)
            ->and($catalog->semesterOf('B.Sc. in Electrical and Electronic Engineering 9th Batch 8th Semester Examination 2020'))->toBe(8)
            ->and($catalog->semesterOf('B.Sc. in Electrical and Electronic Engineering 1st year 1st Improvement Semester Examination of 2022 (Retake/Improvement)'))->toBe(1)
            ->and($catalog->kindOf('B.Sc. in Computer Science and Engineering 3rd year 2nd Semester Improvement Examination of 2024 (Retake/Improvement)'))->toBe(PortalExamKind::Improvement)
            ->and($catalog->kindOf('B.Sc. in CSE 4th year 2nd Semester Special Improvement Examination of 2024 (Special Improvement)'))->toBe(PortalExamKind::SpecialImprovement)
            ->and($catalog->kindOf('B.Sc. in CSE 1st year 2nd Semester Examination of 2024'))->toBe(PortalExamKind::Regular);
    });
});

describe('retake and improvement', function () {
    function importFor(Student $student, array $rows): array
    {
        $pull = ResultPull::factory()->create(['student_id' => $student->id]);

        return app(PortalResultImporter::class)->import($student, $pull, $rows);
    }

    it('replaces a failed grade with the later pass and marks it as a retake', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);

        importFor($student, [portalRow(100, 'CSE-3101', 'F', 0.0)]);
        $report = importFor($student, [portalRow(200, 'CSE-3101', 'C', 2.5, PortalExamKind::Improvement)]);

        $current = PortalResult::query()->where('student_id', $student->id)->current()->sole();

        expect($report['changed'])->toBe(1)
            ->and($current->portal_exam_id)->toBe(200)
            ->and($current->change_type)->toBe(PortalChangeType::Retake)
            ->and($current->previous_letter)->toBe('F')
            ->and(PortalResult::query()->where('student_id', $student->id)->count())->toBe(2)
            ->and(PortalResult::query()->where('student_id', $student->id)->where('portal_exam_id', 100)->value('is_current'))->toBeFalse();
    });

    it('marks a better improvement grade as improved and a worse one as declined', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);

        importFor($student, [portalRow(100, 'CSE-3102', 'B', 3.0), portalRow(100, 'CSE-3103', 'A', 4.0)]);
        importFor($student, [portalRow(200, 'CSE-3102', 'A', 3.75, PortalExamKind::Improvement), portalRow(200, 'CSE-3103', 'B', 3.0, PortalExamKind::Improvement)]);

        $byCourse = PortalResult::query()->where('student_id', $student->id)->current()->get()->keyBy('course_code');

        expect($byCourse['CSE-3102']->change_type)->toBe(PortalChangeType::Improved)
            ->and($byCourse['CSE-3103']->change_type)->toBe(PortalChangeType::Declined)
            ->and($byCourse['CSE-3103']->letter)->toBe('B');
    });

    it('can keep the better grade when the policy is "better"', function () {
        config(['result_portal.replace_policy' => 'better']);
        $student = studentFor(T::STUDENT_ELIGIBLE);

        importFor($student, [portalRow(100, 'CSE-3104', 'A', 4.0)]);
        importFor($student, [portalRow(200, 'CSE-3104', 'C', 2.5, PortalExamKind::Improvement)]);

        $current = PortalResult::query()->where('student_id', $student->id)->current()->sole();

        expect($current->portal_exam_id)->toBe(100)
            ->and(PortalResult::query()->where('portal_exam_id', 200)->sole()->change_type)->toBe(PortalChangeType::Declined);
    });

    it('does nothing when the same rows are pulled again', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);
        $rows = [portalRow(100, 'CSE-3105', 'B', 3.0)];

        importFor($student, $rows);
        $second = importFor($student, $rows);

        expect($second['changed'])->toBe(0)->and(PortalResult::query()->where('student_id', $student->id)->count())->toBe(1);
    });
});

describe('auto pull when a student is added', function () {
    it('queues, runs and records a successful pull for a student added through the panel service', function () {
        $this->source->willReturn([portalRow(100, 'CSE-2101', 'A', 4.0), portalRow(100, 'CSE-2102', 'B', 3.0)], examsChecked: 4);
        $this->actingAs(datasetUser(T::SUPER_ADMIN));

        app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), newStudentPayload());

        $student = Student::query()->where('registration_number', '2022954853')->firstOrFail();
        $pull = ResultPull::query()->where('student_id', $student->id)->sole();

        expect($pull->status)->toBe(ResultPullStatus::Success)
            ->and($pull->trigger)->toBe('student_created')
            ->and($pull->results_found)->toBe(2)
            ->and($pull->exams_checked)->toBe(4)
            ->and($pull->requested_by)->toBe(datasetUser(T::SUPER_ADMIN)->id)
            ->and(PortalResult::query()->where('student_id', $student->id)->current()->count())->toBe(2);
    });

    it('also fires when the student is added through an MCP tool', function () {
        $this->source->willReturn([portalRow(100, 'CSE-2101', 'A', 4.0)]);
        ['token' => $token] = mcpIntegrationFor(datasetUser(T::SUPER_ADMIN));

        $response = (new McpClient($this, $token))->call('student_create', newStudentPayload(['email' => 'via.mcp@fec.test', 'roll_number' => 'R-9002', 'registration_number' => '2022954854']));

        expect($response['isError'])->toBeFalse();

        $student = Student::query()->where('registration_number', '2022954854')->firstOrFail();

        expect(ResultPull::query()->where('student_id', $student->id)->sole()->status)->toBe(ResultPullStatus::Success)
            ->and($this->source->fetchedStudentIds)->toContain($student->id);
    });

    it('records a failed pull with the reason instead of losing it', function () {
        $this->source->willFail(ResultPortalException::layoutUnknown());

        app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), newStudentPayload());

        $pull = ResultPull::query()->sole();

        expect($pull->status)->toBe(ResultPullStatus::Failed)
            ->and($pull->message)->toContain('not recognised')
            ->and($pull->finished_at)->not->toBeNull();
    });

    it('records a skipped pull when the department has no portal program', function () {
        config(['result_portal.department_programs' => []]);

        app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), newStudentPayload());

        $pull = ResultPull::query()->sole();

        expect($pull->status)->toBe(ResultPullStatus::Skipped)->and($pull->message)->toContain('not mapped')
            ->and($this->source->fetchedStudentIds)->toBe([]);
    });

    it('records nothing when the sync is switched off', function () {
        config(['result_portal.enabled' => false]);

        app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), newStudentPayload());

        expect(ResultPull::query()->count())->toBe(0);
    });

    it('pulls again when the registration number changes', function () {
        app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), newStudentPayload());
        $student = Student::query()->where('registration_number', '2022954853')->firstOrFail();

        $student->update(['registration_number' => '2022000001']);

        expect(ResultPull::query()->where('student_id', $student->id)->pluck('trigger')->all())->toBe(['student_created', 'registration_changed']);
    });
});

describe('who may read the pulled grades', function () {
    it('lets a student read their own portal grades but not another student\'s', function () {
        $own = studentFor(T::STUDENT_ELIGIBLE);
        $other = studentFor(T::STUDENT_LIBRARY_LOAN);
        PortalResult::factory()->create(['student_id' => $own->id, 'course_code' => 'CSE-1101']);
        PortalResult::factory()->create(['student_id' => $other->id, 'course_code' => 'CSE-1102']);

        $service = app(ResultPullService::class);
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        expect($service->currentResults($student, $own)->pluck('course_code')->all())->toBe(['CSE-1101'])
            ->and(fn () => $service->currentResults($student, $other))->toThrow(App\Exceptions\Domain\ForbiddenException::class);
    });
});

describe('what the student sees', function () {
    it('shows pulled grades on the Results page with the improved / retake mark', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);
        $pull = ResultPull::factory()->create(['student_id' => $student->id]);
        app(PortalResultImporter::class)->import($student, $pull, [portalRow(100, 'CSE-3101', 'F', 0.0), portalRow(100, 'CSE-3102', 'B', 3.0)]);
        app(PortalResultImporter::class)->import($student, $pull, [portalRow(200, 'CSE-3101', 'C', 2.5, PortalExamKind::Improvement)]);

        Livewire::actingAs(datasetUser(T::STUDENT_ELIGIBLE))
            ->test(App\Filament\Pages\Results\MyResults::class)
            ->assertSeeHtml('data-testid="portal-results"')
            ->assertSee('CSE-3101')
            ->assertSee('Retake (F → C)');
    });
});

describe('status labels', function () {
    it('has a readable label for every pull status and leaves the clearance labels alone', function () {
        foreach (ResultPullStatus::cases() as $status) {
            expect($status->label())->not->toStartWith('erp.', $status->value);
        }

        expect(ResultPullStatus::Pending->label())->toBe('Waiting for result')
            ->and(__('erp.clearance.stage_statuses.pending'))->toBe('Pending');
    });
});

describe('the Student results screen', function () {
    it('lets the admin office see successes and failures and retry the failed ones', function () {
        $service = app(ResultPullService::class);
        $this->source->willFail(ResultPortalException::layoutUnknown())->willReturn([portalRow(100, 'CSE-1101', 'A', 4.0)]);

        Student::query()->whereKey(studentFor(T::STUDENT_ELIGIBLE)->id)->update(['registration_number' => '2022000011']);
        Student::query()->whereKey(studentFor(T::STUDENT_NON_RESIDENT)->id)->update(['registration_number' => '2022000012']);

        $failed = $service->queueFor(studentFor(T::STUDENT_ELIGIBLE), 'manual');
        $success = $service->queueFor(studentFor(T::STUDENT_NON_RESIDENT), 'manual');

        $admin = datasetUser(T::ADMIN_OFFICE);

        expect($failed->fresh()->status)->toBe(ResultPullStatus::Failed)->and($success->fresh()->status)->toBe(ResultPullStatus::Success)
            ->and($service->counts($admin))->toMatchArray(['success' => 1, 'failed' => 1]);

        Livewire::actingAs($admin)
            ->test(ListResultPulls::class)
            ->assertCanSeeTableRecords([$failed, $success])
            ->filterTable('status', ResultPullStatus::Failed->value)
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$success]);

        $this->source->willReturn([portalRow(101, 'CSE-1102', 'B', 3.0)]);
        Livewire::actingAs($admin)->test(ListResultPulls::class)->callTableAction('retry', $failed);

        expect(ResultPull::query()->where('student_id', $failed->student_id)->latest('id')->first()->status)->toBe(ResultPullStatus::Success)
            ->and($service->counts($admin)['failed'])->toBe(0);
    });

    it('shows a department head only the students of their own department', function () {
        $service = app(ResultPullService::class);
        $ownStudent = studentFor(T::STUDENT_ELIGIBLE);
        $otherStudent = studentFor(T::STUDENT_LIBRARY_LOAN);

        Student::query()->whereKey($ownStudent->id)->update(['registration_number' => '2022000013']);
        Student::query()->whereKey($otherStudent->id)->update(['registration_number' => '2022000014']);
        $ownStudent->refresh();
        $otherStudent->refresh();

        $own = $service->queueFor($ownStudent, 'manual');
        $other = $service->queueFor($otherStudent, 'manual');

        $head = datasetUser($ownStudent->department->code === T::DEPT_CSE ? T::DEPT_HEAD_CSE : T::DEPT_HEAD_EEE);

        Livewire::actingAs($head)->test(ListResultPulls::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('keeps the screen away from students and teachers', function () {
        $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/student-results')->assertForbidden();
        $this->flushSession();
        $this->actingAs(datasetUser(T::TEACHER))->get('/student-results')->assertForbidden();
    });
});
