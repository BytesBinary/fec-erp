<?php

namespace App\Services\ResultPortal;

use App\Models\Student;

/**
 * Which exam years a student can have results in: from the admission year for
 * the programme length plus extra years for retakes, capped at the current
 * year (2022 → 2022..2026, 2000 → 2000..2006).
 */
class ExamWindow
{
    /**
     * @return array{from: int, to: int}|null
     */
    public function for(Student $student): ?array
    {
        $admission = $student->admissionYear();

        if ($admission === null) {
            return null;
        }

        $to = $admission + (int) config('result_portal.window.program_years') + (int) config('result_portal.window.extra_years');

        return ['from' => $admission, 'to' => min($to, (int) now()->year)];
    }

    public function covers(Student $student, ?int $examYear): bool
    {
        $window = $this->for($student);

        if ($window === null || $examYear === null) {
            return true;
        }

        return $examYear >= $window['from'] && $examYear <= $window['to'];
    }
}
