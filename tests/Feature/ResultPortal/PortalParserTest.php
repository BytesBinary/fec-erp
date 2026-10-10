<?php

use App\Exceptions\Domain\ResultPortalException;
use App\Services\ResultPortal\DuResultParser;
use App\Services\ResultPortal\ExamCatalog;
use App\Services\ResultPortal\ExamWindow;
use App\Services\ResultPortal\PortalPageStatus;
use Carbon\Carbon;

describe('the result page parser (real pages, anonymised)', function () {
    it('reads a regular result with GPA and CGPA', function () {
        $page = (new DuResultParser)->parse(portalFixture('regular_1st_year_1st_sem'));

        expect($page->status)->toBe(PortalPageStatus::Found)
            ->and($page->registration())->toBe('2022000001')
            ->and($page->meta['Session'])->toBe('2022-2023')
            ->and($page->meta['Result Publication Date'])->toBe('13-11-2024')
            ->and($page->subjects)->toHaveCount(9)
            ->and($page->subjects[0])->toBe(['code' => 'CSE-1101', 'title' => 'Fundamentals of Computers and Computing', 'letter' => 'A', 'point' => 3.75])
            ->and($page->outcome)->toBe('Promoted')
            ->and($page->cgpa)->toBe(3.21)
            ->and($page->gpa)->toBeNull()
            ->and($page->backlog)->toBe([]);
    });

    it('reads failures, writes codes the same way, and lists the backlog subjects', function () {
        $page = (new DuResultParser)->parse(portalFixture('regular_with_failures'));
        $byCode = collect($page->subjects)->keyBy('code');

        expect($byCode['CSE-1201']['letter'])->toBe('A-')
            ->and($byCode['PHY-1203'])->toMatchArray(['letter' => 'F', 'point' => 0.0])
            ->and($byCode['MATH-1204']['point'])->toBe(0.0)
            ->and($byCode->has('ENG-1205'))->toBeTrue()
            ->and($page->outcome)->toBe('Promoted')
            ->and($page->backlog)->toBe(['PHY-1203', 'MATH-1204'])
            ->and($page->gpa)->toBe(2.53)
            ->and($page->cgpa)->toBe(2.86);
    });

    it('reads a promoted page that shows no GPA at all', function () {
        $page = (new DuResultParser)->parse(portalFixture('regular_no_gpa'));

        expect($page->subjects)->toHaveCount(10)
            ->and($page->outcome)->toBe('Promoted')
            ->and($page->gpa)->toBeNull()->and($page->cgpa)->toBeNull()
            ->and(collect($page->subjects)->firstWhere('code', 'CSE-2103')['point'])->toBe(0.0);
    });

    it('reads an improvement page that only lists the retaken subjects', function () {
        $page = (new DuResultParser)->parse(portalFixture('improvement'));

        expect(array_column($page->subjects, 'code'))->toBe(['PHY-1203', 'MATH-1204'])
            ->and($page->outcome)->toBe('Imp.')
            ->and($page->meta['Exam Year'])->toBe('2024')
            ->and($page->cgpa)->toBeNull();
    });

    it('recognises the fixed "not verified" answer', function () {
        expect((new DuResultParser)->parse(portalFixture('not_verified'))->status)->toBe(PortalPageStatus::NotVerified);
    });

    it('fails loudly on a page it does not recognise instead of guessing', function () {
        expect(fn () => (new DuResultParser)->parse('<html><body><p>Maintenance</p></body></html>'))->toThrow(ResultPortalException::class, 'not recognised')
            ->and(fn () => (new DuResultParser)->parse(''))->toThrow(ResultPortalException::class);
    });

    it('normalises course codes', function () {
        $parser = new DuResultParser;

        expect($parser->normaliseCode('CSE 1201'))->toBe('CSE-1201')
            ->and($parser->normaliseCode(' cse-1201 '))->toBe('CSE-1201')
            ->and($parser->normaliseCode('MATH  1204'))->toBe('MATH-1204');
    });
});

describe('exam titles and the admission-year window', function () {
    it('reads the exam year and the session tag from messy titles', function () {
        $catalog = app(ExamCatalog::class);

        expect($catalog->examYearOf('B.Sc. in CSE 1st Year 1st Semester Examination 2020 (2019-2020)'))->toBe(2020)
            ->and($catalog->examYearOf('B.Sc. in CSE 4th year 1st Semester Examination of  2021'))->toBe(2021)
            ->and($catalog->examYearOf('B.Sc. in CSE 3rd year 2nd Semester Improvement Examination of 2024 (Retake/Improvement)'))->toBe(2024)
            ->and($catalog->examYearOf('Something odd'))->toBeNull()
            ->and($catalog->sessionTagOf('1st Year 1st Semester B.Sc. in CSE Improvement Examination 2020 (2018-2019  )'))->toBe('2018-2019')
            ->and($catalog->sessionTagOf('B.Sc. in CSE 4th year 1st Semester Examination of 2024 (2020-2021)'))->toBe('2020-2021')
            ->and($catalog->sessionTagOf('B.Sc. in CSE 3rd year 2nd Semester Examination of 2024'))->toBeNull()
            ->and($catalog->sessionTagOf('B.Sc. in CSE 3rd year 2nd Semester (Retake/Improvement)'))->toBeNull();
    });

    it('runs from the admission year for the programme plus two retake years, never past today', function () {
        Carbon::setTestNow('2026-10-10');
        $window = new ExamWindow;
        $student = studentForWindow(2022);

        expect($window->for($student))->toBe(['from' => 2022, 'to' => 2026])
            ->and($window->for(studentForWindow(2000)))->toBe(['from' => 2000, 'to' => 2006])
            ->and($window->for(studentForWindow(2024)))->toBe(['from' => 2024, 'to' => 2026])
            ->and($window->covers($student, 2021))->toBeFalse()
            ->and($window->covers($student, 2022))->toBeTrue()
            ->and($window->covers($student, 2027))->toBeFalse()
            ->and($window->covers($student, null))->toBeTrue();
    });

    it('falls back to the batch session start year when no admission year is entered', function () {
        $student = studentForWindow(null, '2021-2022');

        expect($student->admissionYear())->toBe(2021)
            ->and(studentForWindow(2023, '2021-2022')->admissionYear())->toBe(2023);
    });

    it('keeps only exams inside the window and not tagged with another session', function () {
        Carbon::setTestNow('2026-10-10');
        $catalog = app(ExamCatalog::class);
        $student = studentForWindow(2022, '2022-2023');

        $listings = $catalog->parse(implode('', [
            '<option value="1">B.Sc. in CSE 1st year 1st Semester Examination of 2023</option>',
            '<option value="2">B.Sc. in CSE 1st year 1st Semester Examination of 2019</option>',
            '<option value="3">B.Sc. in CSE 4th year 1st Semester Examination of 2024 (2020-2021)</option>',
            '<option value="4">B.Sc. in CSE 1st year 2nd Semester Improvement Examination of 2024 (Retake/Improvement)</option>',
            '<option value="5">B.Sc. in CSE 1st year 1st Semester Examination of 2027</option>',
            '<option value="6">Odd title without a year</option>',
        ]));

        expect(array_map(fn ($listing) => $listing->id, $catalog->applicable($listings, $student)))->toBe([1, 4, 6]);
    });
});

function studentForWindow(?int $admissionYear, string $session = '2022-2023'): App\Models\Student
{
    $student = new App\Models\Student(['admission_year' => $admissionYear]);
    $student->setRelation('batch', new App\Models\Batch(['session' => $session]));

    return $student;
}
