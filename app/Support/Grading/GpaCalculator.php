<?php

namespace App\Support\Grading;

use Illuminate\Container\Container;

/**
 * The single, pure grade-point calculation used by the web UI, MCP and the
 * assistant (spec §7).
 *
 * GPA  = Σ(grade_point × credit) / Σ(credit) over the given attempts.
 * CGPA = the same formula over one counted attempt per course (best or
 *        latest, see config/grading.php); failed courses without a passing
 *        attempt count as 0.00 with their credits when `failedCountsInCgpa`.
 *        Zero-credit courses never count.
 */
final class GpaCalculator
{
    /**
     * Semester GPA: every attempt graded in the semester counts.
     *
     * @param  list<GradedCourse>  $courses
     */
    public static function semesterGpa(array $courses, float $passAbove = 0.0): GpaSummary
    {
        return self::summarise(array_values(array_filter($courses, fn (GradedCourse $course): bool => $course->credits > 0)), $passAbove);
    }

    /**
     * @param  list<GradedCourse>  $courses  all attempts across all published semesters
     * @param  'best'|'latest'  $retakePolicy
     */
    public static function cgpa(array $courses, string $retakePolicy = 'best', bool $failedCountsInCgpa = true, float $passAbove = 0.0): GpaSummary
    {
        $byCourse = [];

        foreach ($courses as $course) {
            if ($course->credits > 0) {
                $byCourse[$course->courseId][] = $course;
            }
        }

        $counted = [];

        foreach ($byCourse as $attempts) {
            $chosen = self::chooseAttempt($attempts, $retakePolicy);

            if ($chosen->gradePoint <= $passAbove && ! $failedCountsInCgpa) {
                continue;
            }

            $counted[] = $chosen;
        }

        return self::summarise($counted, $passAbove);
    }

    /**
     * Rounds half up to `$decimals` places; null renders as an em dash.
     */
    public static function format(?float $value, ?int $decimals = null): string
    {
        if ($value === null) {
            return '—';
        }

        $decimals ??= Container::getInstance()->bound('config') ? (int) config('grading.decimals', 2) : 2;

        return number_format(round($value + 1e-9, $decimals), $decimals, '.', '');
    }

    /**
     * @param  non-empty-list<GradedCourse>  $attempts
     */
    protected static function chooseAttempt(array $attempts, string $retakePolicy): GradedCourse
    {
        usort($attempts, fn (GradedCourse $a, GradedCourse $b): int => $a->attemptOrder <=> $b->attemptOrder);

        if ($retakePolicy === 'latest') {
            return $attempts[array_key_last($attempts)];
        }

        $best = $attempts[0];

        foreach ($attempts as $attempt) {
            if ($attempt->gradePoint >= $best->gradePoint) {
                $best = $attempt;
            }
        }

        return $best;
    }

    /**
     * @param  list<GradedCourse>  $courses
     */
    protected static function summarise(array $courses, float $passAbove): GpaSummary
    {
        $credits = 0.0;
        $points = 0.0;
        $earned = 0.0;

        foreach ($courses as $course) {
            $credits += $course->credits;
            $points += $course->credits * $course->gradePoint;

            if ($course->gradePoint > $passAbove) {
                $earned += $course->credits;
            }
        }

        return new GpaSummary($credits > 0 ? $points / $credits : null, $credits, $earned, $points);
    }
}
