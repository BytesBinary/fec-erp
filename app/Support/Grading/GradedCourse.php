<?php

namespace App\Support\Grading;

/**
 * One graded attempt of a course, the input of {@see GpaCalculator}.
 * `attemptOrder` increases chronologically (later attempt = larger value).
 */
final readonly class GradedCourse
{
    public function __construct(
        public int $courseId,
        public float $credits,
        public float $gradePoint,
        public int $attemptOrder = 0,
    ) {}
}
