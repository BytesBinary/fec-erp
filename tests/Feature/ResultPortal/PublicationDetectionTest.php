<?php

use App\Enums\ResultPullStatus;
use App\Events\Portal\ExamCatalogUpdated;
use App\Events\Portal\PortalHealthFailed;
use App\Events\Portal\PublicationConfirmed;
use App\Events\Portal\PublicationDetected;
use App\Models\Batch;
use App\Models\PortalCheckRun;
use App\Models\PortalExam;
use App\Models\PortalExamResult;
use App\Models\PortalProbe;
use App\Models\PortalPublication;
use App\Models\ResultPull;
use App\Services\ResultPortal\PublicationDetector;
use Carbon\Carbon;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const OLD_EXAMS = '<option value="1221">B.Sc. in Computer Science and Engineering 1st year 2nd Semester Examination of 2023</option><option value="966">B.Sc. in Computer Science and Engineering 1st year 1st Semester Examination of 2023</option>';
const NEW_EXAM = '<option value="1563">B.Sc. in Computer Science and Engineering 2nd year 1st Semester Examination of 2024</option>';

beforeEach(function () {
    seedTestDataset();
    Carbon::setTestNow('2026-10-10 09:00:00');
    Storage::fake('local');
    Cache::forget('result_portal.sessions');
    Batch::query()->update(['session' => '2022-2023']);

    foreach ([T::STUDENT_ELIGIBLE => '2022000001', T::STUDENT_NON_RESIDENT => '2022000002', T::STUDENT_UNFINISHED => '2022000003', T::STUDENT_LIBRARY_LOAN => '2022000004'] as $email => $reg) {
        studentFor($email)->update(['registration_number' => $reg, 'admission_year' => null]);
    }

    config([
        'result_portal.enabled' => true,
        'result_portal.shadow_mode' => false,
        'result_portal.request_delay_ms' => 0,
        'result_portal.department_programs' => [T::DEPT_CSE => 14, T::DEPT_EEE => 13, 'CE' => 12],
    ]);

    $this->lists = [
        14 => OLD_EXAMS,
        13 => '<option value="2221">B.Sc. in Electrical and Electronic Engineering 1st year 2nd Semester Examination of 2023</option><option value="2966">B.Sc. in Electrical and Electronic Engineering 1st year 1st Semester Examination of 2023</option>',
        12 => '<option value="3221">B.Sc. in Civil Engineering 1st year 2nd Semester Examination of 2023</option><option value="3966">B.Sc. in Civil Engineering 1st year 1st Semester Examination of 2023</option>',
    ];
    $this->mode = 'normal';
    $this->visibleFor = [];

    Http::fake(function (Request $request) {
        if ($this->mode === 'down' || ($this->mode === 'probe_down' && $request->method() === 'POST')) {
            return Http::response('down', 503);
        }

        if (str_contains($request->url(), 'result.php')) {
            return Http::response('<option value="23">2022-2023</option>');
        }

        if ($request->method() === 'GET') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response($this->mode === 'empty' ? '' : ($this->lists[(int) $query['program_id']] ?? ''));
        }

        if ($this->mode === 'garbage') {
            return Http::response('<html>Maintenance</html>');
        }

        $visible = in_array($request['reg_no'], $this->visibleFor, true);

        return Http::response($visible ? str_replace('2022000001', $request['reg_no'], portalFixture('regular_no_gpa')) : portalFixture('not_verified'));
    });
});

function detector(): PublicationDetector
{
    return app(PublicationDetector::class);
}

function publishNewExam(): void
{
    $lists = test()->lists;
    $lists[14] = OLD_EXAMS.NEW_EXAM;
    test()->lists = $lists;
}

describe('first-time sync and detecting a new exam', function () {
    it('saves the exam list as known on the first sync and detects nothing', function () {
        Event::fake([PublicationDetected::class, ExamCatalogUpdated::class]);

        $report = detector()->run();

        expect($report)->toMatchArray(['new_exams' => 0, 'confirmed' => 0, 'failed' => false])
            ->and(PortalExam::query()->count())->toBe(6)
            ->and(PortalExam::query()->where('status', 'known')->count())->toBe(6)
            ->and(PortalExam::query()->where('portal_exam_id', 1221)->value('status'))->toBe('known')
            ->and(PortalPublication::query()->count())->toBe(0);

        Event::assertNotDispatched(PublicationDetected::class);
    });

    it('detects an exam id that was not on the list before and records it as a publication', function () {
        detector()->run();
        publishNewExam();
        $this->visibleFor = [];
        Event::fake([PublicationDetected::class, ExamCatalogUpdated::class]);

        $report = detector()->run();

        $publication = PortalPublication::query()->sole();

        expect($report['new_exams'])->toBe(1)
            ->and($publication->portal_exam_id)->toBe(1563)
            ->and($publication->status)->toBe('awaiting')
            ->and(PortalExam::query()->where('portal_exam_id', 1563)->value('status'))->toBe('detected');

        Event::assertDispatched(PublicationDetected::class);
        Event::assertDispatched(ExamCatalogUpdated::class);
    });
});

describe('confirming with probe students', function () {
    it('confirms when a probe student sees the result and queues only the eligible students', function () {
        detector()->run();
        publishNewExam();
        $this->visibleFor = ['2022000001', '2022000002', '2022000003'];
        Event::fake([PublicationConfirmed::class]);

        $report = detector()->run();

        $publication = PortalPublication::query()->sole();
        $pulledStudents = ResultPull::query()->where('portal_publication_id', $publication->id)->pluck('student_id')->all();

        expect($report['confirmed'])->toBe(1)
            ->and($publication->status)->toBe('confirmed')
            ->and($publication->students_total)->toBe(3)
            ->and($pulledStudents)->toEqualCanonicalizing([studentFor(T::STUDENT_ELIGIBLE)->id, studentFor(T::STUDENT_NON_RESIDENT)->id, studentFor(T::STUDENT_UNFINISHED)->id])
            ->and(ResultPull::query()->where('portal_exam_id', 1563)->where('status', ResultPullStatus::Success->value)->count())->toBe(3)
            ->and(PortalExam::query()->where('portal_exam_id', 1563)->value('status'))->toBe('published')
            ->and(PortalExam::query()->where('portal_exam_id', 1563)->value('published_on')->toDateString())->toBe('2026-02-02');

        Event::assertDispatched(PublicationConfirmed::class);
    });

    it('is confirmed by the second probe when the first one has no result yet', function () {
        detector()->run();
        publishNewExam();

        $first = studentFor(T::STUDENT_ELIGIBLE);
        $second = studentFor(T::STUDENT_NON_RESIDENT);
        PortalExamResult::factory()->create(['student_id' => $first->id, 'cgpa' => 3.9]);
        PortalExamResult::factory()->create(['student_id' => $second->id, 'cgpa' => 3.5]);
        $this->visibleFor = ['2022000002'];

        detector()->run();

        $outcomes = PortalProbe::query()->orderBy('id')->pluck('outcome', 'student_id')->all();

        expect($outcomes)->toBe([$first->id => 'not_verified', $second->id => 'found'])
            ->and(PortalPublication::query()->sole()->status)->toBe('confirmed');
    });

    it('keeps an exam awaiting when nobody sees a result, re-checks it daily, and weekly after two weeks', function () {
        detector()->run();
        publishNewExam();
        detector()->run();

        $publication = PortalPublication::query()->sole();

        expect($publication->status)->toBe('awaiting')
            ->and($publication->next_check_at->toDateString())->toBe('2026-10-11')
            ->and(ResultPull::query()->count())->toBe(0);

        Carbon::setTestNow('2026-10-11 09:00:00');
        detector()->run();
        expect(PortalProbe::query()->count())->toBe(4);

        Carbon::setTestNow('2026-10-30 09:00:00');
        $this->visibleFor = [];
        detector()->run();
        expect($publication->fresh()->next_check_at->toDateString())->toBe('2026-11-06');
    });

    it('does not treat a portal error as "not published" and raises a health event', function () {
        detector()->run();
        publishNewExam();
        Event::fake([PortalHealthFailed::class]);
        $this->mode = 'probe_down';

        detector()->run();

        $probe = PortalProbe::query()->first();

        expect($probe->outcome)->toBe('error')
            ->and(PortalPublication::query()->sole()->notes)->toContain('not proof that nothing is published');

        Event::assertDispatched(PortalHealthFailed::class);
    });

    it('does not confirm anything on a page it cannot read', function () {
        detector()->run();
        publishNewExam();
        $this->mode = 'garbage';
        Event::fake([PortalHealthFailed::class, PublicationConfirmed::class]);

        detector()->run();

        expect(PortalProbe::query()->first()->outcome)->toBe('unrecognised')
            ->and(PortalPublication::query()->sole()->status)->toBe('awaiting')
            ->and(ResultPull::query()->count())->toBe(0);

        Event::assertDispatched(PortalHealthFailed::class);
        Event::assertNotDispatched(PublicationConfirmed::class);
    });

    it('fails the whole check on an outage or an empty exam list instead of reporting "no news"', function () {
        Event::fake([PortalHealthFailed::class]);

        $this->mode = 'down';
        expect(detector()->run())->toMatchArray(['failed' => true]);

        $this->mode = 'empty';
        $report = detector()->run();

        expect($report['failed'])->toBeTrue()->and($report['message'])->toContain('empty exam list')
            ->and(PortalCheckRun::query()->where('status', 'failed')->count())->toBe(2)
            ->and(PortalExam::query()->count())->toBe(0);

        Event::assertDispatched(PortalHealthFailed::class, 2);
    });
});

describe('repeated checks and late results', function () {
    it('does nothing on a repeated daily check: no new publication, no duplicate pulls', function () {
        detector()->run();
        publishNewExam();
        $this->visibleFor = ['2022000001', '2022000002', '2022000003'];

        detector()->run();
        $pulls = ResultPull::query()->count();
        $results = App\Models\PortalResult::query()->count();

        $again = detector()->run();

        expect($again)->toMatchArray(['new_exams' => 0, 'confirmed' => 0])
            ->and(PortalPublication::query()->count())->toBe(1)
            ->and(ResultPull::query()->count())->toBe($pulls)
            ->and(App\Models\PortalResult::query()->count())->toBe($results);
    });

    it('shadow mode confirms but pulls nothing until an admin presses Run now', function () {
        config(['result_portal.shadow_mode' => true]);
        detector()->run();
        publishNewExam();
        PortalExamResult::factory()->create(['student_id' => studentFor(T::STUDENT_ELIGIBLE)->id, 'cgpa' => 3.9]);
        $this->visibleFor = ['2022000001'];

        detector()->run();

        $publication = PortalPublication::query()->sole();

        expect($publication->status)->toBe('shadow')->and($publication->mode)->toBe('shadow')->and(ResultPull::query()->count())->toBe(0);

        $queued = app(App\Services\ResultPortal\PortalMonitor::class)->runPublication(datasetUser(T::ADMIN_OFFICE), $publication);

        expect($queued)->toBe(3)->and($publication->fresh()->status)->toBe('confirmed')->and(ResultPull::query()->count())->toBe(3);
    });

    it('marks students without a result as waiting, re-checks them twice, then closes them as not in this exam', function () {
        detector()->run();
        publishNewExam();
        PortalExamResult::factory()->create(['student_id' => studentFor(T::STUDENT_ELIGIBLE)->id, 'cgpa' => 3.9]);
        $this->visibleFor = ['2022000001'];
        detector()->run();

        $waiting = ResultPull::query()->where('portal_exam_id', 1563)->where('status', ResultPullStatus::Pending->value)->get();

        expect($waiting)->toHaveCount(2)
            ->and($waiting->first()->message)->toContain('check 1 of 2')
            ->and($waiting->first()->next_check_at->toDateString())->toBe('2026-10-13');

        Artisan::call('portal:recheck-pending');
        expect(ResultPull::query()->where('status', ResultPullStatus::Pending->value)->count())->toBe(2);

        Carbon::setTestNow('2026-10-13 09:00:00');
        $this->visibleFor = ['2022000001', '2022000002'];
        Artisan::call('portal:recheck-pending');

        expect(ResultPull::query()->where('status', ResultPullStatus::Pending->value)->count())->toBe(1)
            ->and(ResultPull::query()->where('portal_exam_id', 1563)->where('status', ResultPullStatus::Success->value)->count())->toBe(2)
            ->and(ResultPull::query()->where('status', ResultPullStatus::Pending->value)->first()->message)->toContain('check 2 of 2');

        Carbon::setTestNow('2026-10-17 09:00:00');
        Artisan::call('portal:recheck-pending');

        $closed = ResultPull::query()->where('student_id', studentFor(T::STUDENT_UNFINISHED)->id)->where('portal_exam_id', 1563)->first();

        expect($closed->status)->toBe(ResultPullStatus::Success)->and($closed->message)->toContain('probably did not sit it');
    });
});
