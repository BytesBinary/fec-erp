<?php

use App\Enums\PortalChangeType;
use App\Enums\ResultPullStatus;
use App\Exceptions\Domain\ResultPortalException;
use App\Models\PortalExamResult;
use App\Models\PortalResult;
use App\Services\ResultPortal\ResultPullService;
use Carbon\Carbon;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedTestDataset();
    Carbon::setTestNow('2026-10-10');
    Storage::fake('local');
    Cache::forget('result_portal.sessions');

    $this->student = studentFor(T::STUDENT_ELIGIBLE);
    $this->student->update(['registration_number' => '2022000001', 'admission_year' => null]);
    $this->student->batch->update(['session' => '2022-2023']);

    config([
        'result_portal.enabled' => true,
        'result_portal.request_delay_ms' => 0,
        'result_portal.department_programs' => [$this->student->department->code => 14],
    ]);

    $this->examOptions = implode('', [
        '<option value="1889">B.Sc. in Computer Science and Engineering 3rd year 2nd Semester Examination of 2024</option>',
        '<option value="1776">B.Sc. in Computer Science and Engineering 1st year 2nd Semester Improvement Examination of 2024 (Retake/Improvement)</option>',
        '<option value="1563">B.Sc. in Computer Science and Engineering 2nd year 1st Semester Examination of 2024</option>',
        '<option value="1221">B.Sc. in Computer Science and Engineering 1st year 2nd Semester Examination of 2023</option>',
        '<option value="966">B.Sc. in Computer Science and Engineering 1st year 1st Semester Examination of 2023</option>',
        '<option value="1769">B.Sc. in Computer Science and Engineering 4th year 1st Semester Examination of 2024 (2020-2021)</option>',
        '<option value="144">B.Sc. in Computer Science and Engineering 1st year 2nd Semester Examination of 2020</option>',
    ]);

    $pages = [966 => 'regular_1st_year_1st_sem', 1221 => 'regular_with_failures', 1563 => 'regular_no_gpa', 1776 => 'improvement', 1889 => 'not_verified'];

    $this->mode = 'normal';

    Http::fake(function (Request $request) use ($pages) {
        if ($this->mode === 'down') {
            return Http::response('down', 503);
        }

        if (str_contains($request->url(), 'result.php')) {
            return Http::response('<option value="23">2022-2023</option><option value="24">2023-2024</option>');
        }

        if ($request->method() === 'GET') {
            return Http::response($this->examOptions);
        }

        return Http::response($this->mode === 'garbage' ? '<html>Maintenance</html>' : portalFixture($pages[(int) $request['exam_id']] ?? 'not_verified'));
    });
});

function portalPosts(): array
{
    return Http::recorded()->filter(fn (array $pair): bool => $pair[0]->method() === 'POST')->map(fn (array $pair): int => (int) $pair[0]['exam_id'])->values()->all();
}

it('pulls a real student end to end: only the exams in the window, grades saved, retakes marked', function () {
    $pull = app(ResultPullService::class)->queueFor($this->student, 'manual');

    expect($pull->fresh()->status)->toBe(ResultPullStatus::Success)
        ->and(portalPosts())->toEqualCanonicalizing([1889, 1776, 1563, 1221, 966]);

    $current = PortalResult::query()->where('student_id', $this->student->id)->current()->get()->keyBy('course_code');

    expect($current['PHY-1203']->exam_title)->toContain('Improvement')
        ->and($current['PHY-1203']->change_type)->toBe(PortalChangeType::Retake)
        ->and($current['PHY-1203']->previous_letter)->toBe('F')
        ->and($current['PHY-1203']->letter)->toBe('D')
        ->and($current['MATH-1204']->change_type)->toBe(PortalChangeType::Retake)
        ->and($current['CSE-2103']->letter)->toBe('F')
        ->and($current['CSE-2103']->change_type)->toBeNull()
        ->and($current['CSE-1101']->grade_point)->toBe(3.75)
        ->and($current)->toHaveCount(28)
        ->and(PortalResult::query()->where('student_id', $this->student->id)->where('course_code', 'PHY-1203')->count())->toBe(2);

    $exams = PortalExamResult::query()->where('student_id', $this->student->id)->get()->keyBy('portal_exam_id');

    expect($exams)->toHaveCount(4)
        ->and($exams[1221]->cgpa)->toBe(2.86)
        ->and($exams[1221]->backlog_codes)->toBe(['PHY-1203', 'MATH-1204'])
        ->and($exams[966]->cgpa)->toBe(3.21)
        ->and($exams[1776]->outcome)->toBe('Imp.')
        ->and($exams[1776]->published_on->toDateString())->toBe('2026-07-27')
        ->and(Storage::disk('local')->exists($exams[1776]->raw_page_path))->toBeTrue();

    $pull = $pull->fresh();
    expect($pull->exams_checked)->toBe(5)->and($pull->message)->toContain('result(s) read');
});

it('does nothing the second time the same pages come back', function () {
    $service = app(ResultPullService::class);
    $service->queueFor($this->student, 'manual');
    $count = PortalResult::query()->count();

    $second = $service->queueFor($this->student, 'manual')->fresh();

    expect($second->status)->toBe(ResultPullStatus::Success)->and($second->results_changed)->toBe(0)
        ->and(PortalResult::query()->count())->toBe($count);
});

it('widens the window with the admission year and still skips exams tagged with another batch', function () {
    $this->student->update(['admission_year' => 2019]);

    app(ResultPullService::class)->queueFor($this->student, 'manual');

    expect(portalPosts())->toContain(144)->and(portalPosts())->not->toContain(1769);
});

it('fails a pull when the portal answers with a result for another registration number', function () {
    $this->student->update(['registration_number' => '2022999999']);

    $pull = app(ResultPullService::class)->queueFor($this->student, 'manual')->fresh();

    expect($pull->status)->toBe(ResultPullStatus::Failed)->and($pull->message)->toContain('another registration number')
        ->and(PortalResult::query()->count())->toBe(0);
});

it('fails visibly on a page it cannot read and saves nothing', function () {
    $this->mode = 'garbage';

    $pull = app(ResultPullService::class)->queueFor($this->student, 'manual')->fresh();

    expect($pull->status)->toBe(ResultPullStatus::Failed)->and($pull->message)->toContain('not recognised')
        ->and(PortalResult::query()->count())->toBe(0);
});

it('treats an unreachable portal as a transient error the queue retries', function () {
    $this->mode = 'down';

    try {
        app(App\Services\ResultPortal\DuPortalResultSource::class)->fetch($this->student);
        $this->fail('expected the portal to be unreachable');
    } catch (ResultPortalException $exception) {
        expect($exception->transient)->toBeTrue();
    }
});

it('uses the saved exam list instead of asking the portal for it', function () {
    foreach ([966, 1221] as $id) {
        App\Models\PortalExam::factory()->create(['portal_exam_id' => $id, 'program_id' => 14, 'title' => "B.Sc. in Computer Science and Engineering 1st year Semester Examination of 2023 #{$id}", 'exam_year' => 2023, 'semester' => 1]);
    }

    app(ResultPullService::class)->queueFor($this->student, 'manual');

    expect(Http::recorded()->filter(fn (array $pair): bool => $pair[0]->method() === 'GET' && str_contains($pair[0]->url(), 'get_program_by_exam'))->count())->toBe(0)
        ->and(portalPosts())->toEqualCanonicalizing([966, 1221]);
});
