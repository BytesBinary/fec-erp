<?php

use App\Support\Grading\GpaCalculator;
use App\Support\Grading\GradedCourse as G;

dataset('cgpa cases', [
    'single course' => [[new G(1, 3, 4.0, 1)], 4.0, 3.0, 3.0],
    'credit weighted' => [[new G(1, 3, 4.0, 1), new G(2, 1.5, 3.0, 2)], (12 + 4.5) / 4.5, 4.5, 4.5],
    'retake: best attempt wins over earlier fail' => [[new G(1, 3, 0.0, 1), new G(1, 3, 3.25, 2), new G(2, 3, 4.0, 3)], (3 * 3.25 + 3 * 4.0) / 6, 6.0, 6.0],
    'improvement: earlier better grade stays' => [[new G(1, 3, 4.0, 1), new G(1, 3, 3.0, 2)], 4.0, 3.0, 3.0],
    'failed course counts as zero with its credits' => [[new G(1, 3, 4.0, 1), new G(2, 3, 0.0, 2)], 2.0, 6.0, 3.0],
    'zero credit course ignored' => [[new G(1, 3, 3.0, 1), new G(2, 0.0, 4.0, 2)], 3.0, 3.0, 3.0],
    'tie keeps later attempt, same value' => [[new G(1, 3, 3.5, 1), new G(1, 3, 3.5, 2)], 3.5, 3.0, 3.0],
]);

it('computes the cgpa from one counted attempt per course', function (array $courses, float $expected, float $counted, float $earned) {
    $summary = GpaCalculator::cgpa($courses);

    expect($summary->gpa)->toEqualWithDelta($expected, 1e-9)
        ->and($summary->countedCredits)->toBe($counted)
        ->and($summary->earnedCredits)->toBe($earned);
})->with('cgpa cases');

it('returns no gpa when nothing counts', function () {
    expect(GpaCalculator::cgpa([])->gpa)->toBeNull()
        ->and(GpaCalculator::cgpa([new G(1, 0.0, 4.0)])->gpa)->toBeNull()
        ->and(GpaCalculator::semesterGpa([])->display())->toBe('—');
});

it('can use the latest attempt instead of the best', function () {
    $courses = [new G(1, 3, 4.0, 1), new G(1, 3, 3.0, 2)];

    expect(GpaCalculator::cgpa($courses, 'latest')->gpa)->toBe(3.0)
        ->and(GpaCalculator::cgpa($courses, 'best')->gpa)->toBe(4.0);
});

it('can leave failed courses without a passing attempt out of the cgpa', function () {
    $courses = [new G(1, 3, 4.0, 1), new G(2, 3, 0.0, 2)];

    expect(GpaCalculator::cgpa($courses, 'best', failedCountsInCgpa: false)->gpa)->toBe(4.0)
        ->and(GpaCalculator::cgpa($courses, 'best', failedCountsInCgpa: true)->gpa)->toBe(2.0);
});

it('counts every attempt of a semester in the semester gpa', function () {
    $semester = [new G(1, 3, 3.25, 5), new G(2, 1.5, 4.0, 6)];

    expect(GpaCalculator::semesterGpa($semester)->gpa)->toBe((3 * 3.25 + 1.5 * 4.0) / 4.5);
});

it('does not count zero-credit courses in a semester gpa', function () {
    expect(GpaCalculator::semesterGpa([new G(1, 3, 3.0), new G(2, 0.0, 0.0)])->gpa)->toBe(3.0);
});

dataset('rounding', [
    [3.5625, '3.56'],
    [3.375, '3.38'],
    [1.625, '1.63'],
    [3.0, '3.00'],
    [2.995, '3.00'],
    [3.994, '3.99'],
    [3.9949999, '3.99'],
    [0.0, '0.00'],
    [3.35, '3.35'],
    [2.675, '2.68'],
]);

it('rounds half up to two decimals for display only', function (float $value, string $expected) {
    expect(GpaCalculator::format($value))->toBe($expected);
})->with('rounding');

it('keeps full precision internally and rounds only the displayed value', function () {
    $summary = GpaCalculator::cgpa([new G(1, 3, 3.25, 1), new G(2, 3, 3.5, 2), new G(3, 3, 3.0, 3)]);

    expect($summary->gpa)->toEqualWithDelta(3.25, 1e-12)
        ->and(GpaCalculator::cgpa([new G(1, 3, 4.0, 1), new G(2, 4.5, 3.25, 2)])->gpa)->toEqualWithDelta((12 + 14.625) / 7.5, 1e-12)
        ->and(GpaCalculator::cgpa([new G(1, 3, 4.0, 1), new G(2, 4.5, 3.25, 2)])->display())->toBe('3.55');
});
