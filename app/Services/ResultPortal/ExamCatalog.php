<?php

namespace App\Services\ResultPortal;

use App\Enums\PortalExamKind;
use App\Models\Student;

/**
 * Reads the portal's exam drop-down (`<option value="id">title</option>`).
 * Titles are free text ("B.Sc. in Computer Science and Engineering 3rd year
 * 2nd Semester Improvement Examination of 2024 (Retake/Improvement)"), so
 * parsing is tolerant of spacing and legacy wording.
 */
class ExamCatalog
{
    public function __construct(protected ExamWindow $window) {}

    /**
     * @return list<ExamListing>
     */
    public function parse(string $optionsHtml): array
    {
        preg_match_all('/<option\s+value="(\d+)">(.*?)<\/option>/s', $optionsHtml, $matches, PREG_SET_ORDER);

        return array_values(array_map(function (array $match): ExamListing {
            $title = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($match[2]))));

            return new ExamListing((int) $match[1], $title, $this->kindOf($title), $this->semesterOf($title), $this->examYearOf($title), $this->sessionTagOf($title));
        }, $matches));
    }

    public function kindOf(string $title): PortalExamKind
    {
        $lower = strtolower(preg_replace('/\s+/', ' ', $title));

        return match (true) {
            str_contains($lower, 'special improvement') => PortalExamKind::SpecialImprovement,
            str_contains($lower, 'improvement') || str_contains($lower, 'retake') || str_contains($lower, 'imp.') => PortalExamKind::Improvement,
            default => PortalExamKind::Regular,
        };
    }

    /**
     * Semester number 1–8 ("3rd year 2nd Semester" → 6, "8th Semester" → 8).
     */
    public function semesterOf(string $title): ?int
    {
        if (preg_match('/(\d)\s*(?:st|nd|rd|th)\s+year\s+(\d)\s*(?:st|nd|rd|th)\s+(?:improvement\s+)?semester/i', $title, $m)) {
            return ((int) $m[1] - 1) * 2 + (int) $m[2];
        }

        if (preg_match('/(\d)\s*(?:st|nd|rd|th)\s+semester/i', $title, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * The year of the exam ("… Examination of 2024", "… Examination 2020").
     */
    public function examYearOf(string $title): ?int
    {
        return preg_match('/Exam(?:ination)?\s*(?:of\s*)?(\d{4})\b/i', $title, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * The batch session written in the title, "2020-2021" → "2020-2021".
     */
    public function sessionTagOf(string $title): ?string
    {
        return preg_match('/\(\s*(?:[A-Za-z\/ .-]+-\s*)?(\d{4})\s*-\s*(\d{4})\s*\)/', $title, $m) === 1 ? "{$m[1]}-{$m[2]}" : null;
    }

    /**
     * The exams worth asking for one student: exam year inside the student's
     * window, and not tagged with another batch's session.
     *
     * @param  list<ExamListing>  $exams
     * @return list<ExamListing>
     */
    public function applicable(array $exams, Student $student): array
    {
        $session = trim((string) $student->batch?->session);

        return array_values(array_filter(
            $exams,
            fn (ExamListing $exam): bool => $this->window->covers($student, $exam->examYear)
                && ($exam->sessionTag === null || $session === '' || $exam->sessionTag === $session),
        ));
    }
}
