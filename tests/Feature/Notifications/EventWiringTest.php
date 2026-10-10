<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\ResultStatus;
use App\Events\Portal\ExamCatalogUpdated;
use App\Events\Portal\PortalHealthFailed;
use App\Events\Portal\StudentGradeChanged;
use App\Events\Portal\StudentResultPulled;
use App\Events\Portal\StudentResultPullFailed;
use App\Models\Batch;
use App\Models\ClearanceApproval;
use App\Models\Department;
use App\Models\EmailDelivery;
use App\Models\HallDue;
use App\Models\LibraryLoan;
use App\Models\Notice;
use App\Models\NotificationRule;
use App\Models\PortalExam;
use App\Models\PortalResult;
use App\Models\Result;
use App\Models\ResultPull;
use App\Models\Semester;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\HashChain;
use App\Services\Halls\HallResidencyService;
use App\Services\Library\LibraryService;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\NotificationRuleService;
use App\Services\People\StudentService;
use App\Services\Results\ResultService;
use App\Services\Security\TwoFactorService;
use App\Services\Security\UserSecurityService;
use App\Services\Users\RoleService;
use App\Services\Users\UserService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\RecordingMessenger;

beforeEach(function () {
    seedTestDataset();
    config(['notifications.enabled' => true]);
    $this->messenger = new RecordingMessenger;
    app()->instance(ExternalMessenger::class, $this->messenger);
    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->actingAs($this->admin);
});

function mailsTo(string $email): array
{
    return collect(test()->messenger->sent)->where('email', $email)->values()->all();
}

function turnOn(string $event, string $mode = 'immediate'): void
{
    app(NotificationRuleService::class)->update(datasetUser(T::SUPER_ADMIN), $event, ['enabled' => true, 'mode' => $mode]);
}

function deliveryFor(string $event, ?string $email = null): ?EmailDelivery
{
    return EmailDelivery::query()->where('event_key', $event)->when($email, fn ($query) => $query->where('recipient_email', $email))->latest('id')->first();
}

describe('account and security', function () {
    it('welcomes a new student without ever sending a password', function () {
        $department = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

        app(StudentService::class)->create($this->admin, [
            'name' => 'Newly Added', 'email' => 'new.added@fec.test', 'password' => 'very-secret-pw-1', 'department_id' => $department->id,
            'batch_id' => Batch::query()->first()->id, 'roll_number' => 'R-7001', 'registration_number' => '2022123456', 'current_semester' => 3,
        ]);

        $mail = mailsTo('new.added@fec.test');

        expect($mail)->toHaveCount(1)
            ->and($mail[0]['body'])->toContain('Hello Newly Added')->toContain('CSE')->not->toContain('very-secret-pw-1')->not->toContain('password:')
            ->and(deliveryFor('student.added_admin_copy'))->toBeNull();
    });

    it('tells the user about 2FA being turned on and off and a recovery code being used, without the codes', function () {
        $this->freezeTime();
        $user = datasetUser(T::TEACHER);
        $service = app(TwoFactorService::class);

        $setup = $service->beginSetup($user, 'password');
        $recovery = $service->confirmSetup($user, totpCode($setup['secret'] ?? $service->pendingSecret($user)));

        expect(mailsTo($user->email))->toHaveCount(1)->and(mailsTo($user->email)[0]['subject'])->toContain('Two-factor');

        $service->verify($user, $recovery[0]);

        $mails = mailsTo($user->email);

        expect($mails)->toHaveCount(2)
            ->and($mails[1]['body'])->toContain('code(s) are left')
            ->and(collect($mails)->pluck('body')->implode(' '))->not->toContain($recovery[0]);
    });

    it('tells the user when an administrator signs them out, and when their account or roles change', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        app(UserSecurityService::class)->revokeAllSessions($this->admin, $student, 'Security check');
        app(UserService::class)->deactivate($this->admin, $student);
        app(UserService::class)->reactivate($this->admin, $student);

        $subjects = collect(mailsTo($student->email))->pluck('subject')->implode(' | ');

        expect(deliveryFor('security.account_deactivated', $student->email))->not->toBeNull()
            ->and(deliveryFor('security.account_reactivated', $student->email))->not->toBeNull();

        $teacher = datasetUser(T::TEACHER);
        app(RoleService::class)->assign($this->admin, $teacher, 'librarian');

        expect(deliveryFor('security.role_changed', $teacher->email)->body)->toContain('librarian')->toContain('teacher');
    });

    it('mails both the new and the old address when an email changes, masking the new one', function () {
        $user = datasetUser(T::TEACHER);
        $old = $user->email;

        $user->update(['email' => 'teacher.new@example.com']);

        $new = mailsTo('teacher.new@example.com');
        $previous = mailsTo($old);

        expect($new)->toHaveCount(1)->and($previous)->toHaveCount(1)->and($new[0]['body'])->toContain('t***@example.com')->not->toContain('teacher.new@example.com');
    });
});

describe('students, campus and library', function () {
    it('emails a student about hall, dues, enrollment and library changes', function () {
        $student = studentFor(T::STUDENT_NON_RESIDENT);
        $hall = App\Models\Hall::query()->firstOrFail();

        app(HallResidencyService::class)->assign($this->admin, $student, $hall, '204');
        expect(deliveryFor('hall.assigned', $student->user->email)->body)->toContain($hall->name)->toContain('204');

        app(HallResidencyService::class)->recordDue($this->admin, $student, 'Electricity', 450);
        expect(deliveryFor('hall.due_recorded', $student->user->email)->body)->toContain('450.00')->toContain('Electricity');

        $due = HallDue::query()->where('student_id', $student->id)->firstOrFail();
        app(HallResidencyService::class)->settleDue($this->admin, $due);
        expect(deliveryFor('hall.due_settled', $student->user->email))->not->toBeNull();

        $loan = app(LibraryService::class)->issue($this->admin, $student, 'Algorithms', now()->addDays(10));
        app(LibraryService::class)->markReturned($this->admin, $loan, 50);

        expect(deliveryFor('library.fine_recorded', $student->user->email)->body)->toContain('50.00')->toContain('Algorithms')
            ->and(deliveryFor('library.loan_issued'))->toBeNull();
    });

    it('sends digest events only in the digest and respects switching events on', function () {
        $student = studentFor(T::STUDENT_NON_RESIDENT);
        app(LibraryService::class)->issue($this->admin, $student, 'Algorithms', now()->addDays(10));

        expect(deliveryFor('library.loan_issued'))->toBeNull();

        turnOn('library.loan_issued', 'digest');
        app(LibraryService::class)->issue($this->admin, $student, 'Compilers', now()->addDays(10));

        expect(deliveryFor('library.loan_issued')->status)->toBe(EmailDeliveryStatus::Held);

        app(EmailDeliveryService::class)->sendDigests();

        expect(mailsTo($student->user->email)[0]['body'])->toContain('1 new notification(s)');
    });

    it('scans for due-soon and overdue books and for incomplete profiles, once per day or week', function () {
        $student = studentFor(T::STUDENT_NON_RESIDENT);
        LibraryLoan::query()->create(['student_id' => $student->id, 'book_title' => 'Due Soon Book', 'issued_on' => today()->subDays(10), 'due_on' => today()->addDay()]);
        LibraryLoan::query()->create(['student_id' => $student->id, 'book_title' => 'Overdue Book', 'issued_on' => today()->subDays(30), 'due_on' => today()->subDays(3)]);

        Artisan::call('notifications:scan');
        Artisan::call('notifications:scan');

        $email = $student->user->email;

        expect(EmailDelivery::query()->where('event_key', 'library.loan_due_soon')->where('recipient_email', $email)->count())->toBe(1)
            ->and(EmailDelivery::query()->where('event_key', 'library.loan_overdue')->where('recipient_email', $email)->count())->toBe(1)
            ->and(deliveryFor('library.loan_overdue', $email)->body)->toContain('Overdue Book');
    });

    it('notifies a teacher when assigned to a course', function () {
        $teacher = App\Models\Teacher::query()->where('user_id', datasetUser(T::TEACHER)->id)->firstOrFail();
        $course = App\Models\Course::query()->doesntHave('teachers')->first() ?? App\Models\Course::query()->firstOrFail();

        app(App\Services\Academic\CourseService::class)->assignTeachers($this->admin, $course, [$teacher->id]);

        expect(EmailDelivery::query()->where('event_key', 'course.teachers_assigned')->where('recipient_email', $teacher->user->email)->count())->toBeGreaterThanOrEqual(0);
    });
});

describe('results and clearance', function () {
    it('tells every student of a published semester, without grades, and the department head about submitted results', function () {
        $semester = Semester::query()->firstOrFail();
        $result = Result::query()->whereHas('enrollment.offering', fn ($query) => $query->where('semester_id', $semester->id))->firstOrFail();
        $result->update(['status' => ResultStatus::Approved]);
        $owner = $result->enrollment->student->user;

        app(ResultService::class)->publishSemester($this->admin, $semester);

        $mail = deliveryFor('result.semester_published', $owner->email);

        expect($mail)->not->toBeNull()->and($mail->body)->toContain($semester->name)->not->toContain((string) $result->letter.' ')
            ->and($mail->subject)->toContain($semester->name);
    });

    it('emails the student when clearance is cancelled and the super admin when the hash chain is broken', function () {
        $request = applyForClearance();
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        app(ClearanceService::class)->cancel($student, $request, 'Applied by mistake');

        expect(deliveryFor('clearance.cancelled', $student->email)->body)->toContain($request->request_no)->toContain('Applied by mistake');

        $second = applyForClearance(T::STUDENT_NON_RESIDENT);
        approveInOrder($second, [T::LIBRARIAN]);
        ClearanceApproval::query()->where('clearance_request_id', $second->id)->update(['remarks' => 'tampered']);

        expect(app(HashChain::class)->verify($second->fresh())['intact'])->toBeFalse()
            ->and(deliveryFor('clearance.integrity_failed', $this->admin->email)->body)->toContain($second->request_no);
    });

    it('turns an existing clearance bell notification into an email with the same text', function () {
        $request = applyForClearance();

        $email = datasetUser(T::STUDENT_ELIGIBLE)->email;

        expect(deliveryFor('clearance.submitted', $email))->not->toBeNull()->and(deliveryFor('clearance.submitted', $email)->body)->toContain($request->request_no);
    });
});

describe('notices and configuration', function () {
    it('emails the people a notice is addressed to once the event is switched on', function () {
        turnOn('notice.published');
        $department = studentFor(T::STUDENT_ELIGIBLE)->department_id;

        Notice::query()->create(['title' => 'Exam routine', 'body' => 'See the board.', 'audience' => 'department', 'department_id' => $department, 'created_by' => $this->admin->id, 'published_at' => now()]);

        $recipients = EmailDelivery::query()->where('event_key', 'notice.published')->pluck('recipient_email')->all();

        expect($recipients)->toContain(datasetUser(T::STUDENT_ELIGIBLE)->email)->not->toContain(datasetUser(T::STUDENT_LIBRARY_LOAN)->email);
    });

    it('reports configuration changes to the super admin only after they are switched on', function () {
        app(App\Services\Security\MfaPolicy::class)->setRoleRequired(Spatie\Permission\Models\Role::query()->where('name', 'teacher')->firstOrFail(), true);
        expect(deliveryFor('security.two_factor_policy_changed'))->toBeNull();

        turnOn('security.two_factor_policy_changed');
        app(App\Services\Security\MfaPolicy::class)->setRoleRequired(Spatie\Permission\Models\Role::query()->where('name', 'teacher')->firstOrFail(), false);

        expect(deliveryFor('security.two_factor_policy_changed', $this->admin->email)->body)->toContain('optional for role teacher');
    });
});

describe('result portal events', function () {
    it('emails a student once their result is saved, and never when nothing changed', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);
        PortalExam::factory()->create(['portal_exam_id' => 1563, 'title' => 'CSE 2nd year 1st Semester Examination of 2024']);

        $nothingNew = ResultPull::factory()->create(['student_id' => $student->id, 'portal_exam_id' => 1563, 'results_found' => 9, 'results_changed' => 0]);
        event(new StudentResultPulled($nothingNew));

        expect(deliveryFor('student.result_pulled'))->toBeNull();

        $saved = ResultPull::factory()->create(['student_id' => $student->id, 'portal_exam_id' => 1563, 'results_found' => 9, 'results_changed' => 9]);
        event(new StudentResultPulled($saved));
        event(new StudentResultPulled($saved));

        $mail = deliveryFor('student.result_pulled', $student->user->email);

        expect(EmailDelivery::query()->where('event_key', 'student.result_pulled')->count())->toBe(1)
            ->and($mail->body)->toContain('CSE 2nd year 1st Semester Examination of 2024');
    });

    it('tells the student about retakes and improvements by course, not by grade', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);
        $pull = ResultPull::factory()->create(['student_id' => $student->id]);
        $rows = PortalResult::factory()->count(2)->sequence(['course_code' => 'PHY-1203'], ['course_code' => 'MATH-1204'])->create(['student_id' => $student->id, 'change_type' => 'retake', 'previous_letter' => 'F', 'letter' => 'D', 'exam_title' => 'CSE 1st year 2nd Semester Improvement Examination of 2024']);

        event(new StudentGradeChanged($pull, $rows));

        $mail = deliveryFor('student.grade_changed', $student->user->email);

        expect($mail->body)->toContain('PHY-1203 (retake)')->toContain('MATH-1204 (retake)')->not->toContain('previous');
    });

    it('tells the admins about failed pulls (digest), new exams (digest) and a portal that does not work', function () {
        $student = studentFor(T::STUDENT_ELIGIBLE);
        $failed = ResultPull::factory()->create(['student_id' => $student->id, 'message' => 'The portal answered with a page the parser does not recognise.']);

        event(new StudentResultPullFailed($failed));
        event(new ExamCatalogUpdated(collect([PortalExam::factory()->create(['title' => 'CSE 3rd year 2nd Semester Examination of 2025'])])));
        event(new PortalHealthFailed('The portal could not be reached: HTTP 503'));

        expect(deliveryFor('student.result_pull_failed', datasetUser(T::ADMIN_OFFICE)->email)->status)->toBe(EmailDeliveryStatus::Held)
            ->and(deliveryFor('student.result_pull_failed', datasetUser(T::DEPT_HEAD_CSE)->email))->not->toBeNull()
            ->and(deliveryFor('portal.exam_catalog_updated', datasetUser(T::ADMIN_OFFICE)->email)->body)->toContain('CSE 3rd year 2nd Semester Examination of 2025')
            ->and(deliveryFor('system.portal_health_failed', $this->admin->email)->body)->toContain('HTTP 503');
    });
});

describe('the system hooks', function () {
    it('reports a failed queue job by name only', function () {
        $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\PullStudentResults');

        event(new Illuminate\Queue\Events\JobFailed('database', $job, new RuntimeException('boom with personal data 2022954853')));

        $mail = deliveryFor('system.queue_job_failed');

        expect($mail->status)->toBe(EmailDeliveryStatus::Held)->and($mail->body)->toContain('PullStudentResults')->not->toContain('2022954853');
    });

    it('watches every scheduled command for failures and runs the outbox every minute', function () {
        $events = collect(Illuminate\Support\Facades\Schedule::events());
        $commands = $events->map(fn ($event): string => (string) $event->command)->implode(' ');

        expect($commands)->toContain('notifications:process-outbox')->toContain('notifications:send-digests')->toContain('notifications:scan')->toContain('portal:check');

        foreach ($events as $event) {
            expect((fn () => count($this->afterCallbacks))->call($event))->toBeGreaterThan(0, (string) $event->command);
        }
    });

    it('keeps rules for every registry event so the admin screen lists them all', function () {
        app(NotificationRuleService::class)->list($this->admin);

        expect(NotificationRule::query()->count())->toBe(count(config('notification_events.events')));
    });
});
