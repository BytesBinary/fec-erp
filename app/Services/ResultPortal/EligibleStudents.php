<?php

namespace App\Services\ResultPortal;

use App\Models\PortalExam;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * The students who could have a result in a portal exam: department mapped to
 * the exam's programme, a registration number, the exam year inside their
 * admission window, and a matching session when the title names one.
 */
class EligibleStudents
{
    public function __construct(protected ExamWindow $window) {}

    /**
     * @return Collection<int, Student>
     */
    public function forExam(PortalExam $exam): Collection
    {
        $codes = array_keys(array_filter(config('result_portal.department_programs'), fn (int $program): bool => $program === $exam->program_id));

        return Student::query()
            ->whereNotNull('registration_number')
            ->whereHas('department', fn ($query) => $query->whereIn('code', $codes))
            ->with(['department', 'batch'])
            ->get()
            ->filter(fn (Student $student): bool => ctype_digit((string) $student->registration_number))
            ->filter(fn (Student $student): bool => $this->window->covers($student, $exam->exam_year)
                && ($exam->session_tag === null || trim((string) $student->batch?->session) === $exam->session_tag))
            ->values();
    }
}
