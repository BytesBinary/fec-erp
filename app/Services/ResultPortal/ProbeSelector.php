<?php

namespace App\Services\ResultPortal;

use App\Enums\PortalExamKind;
use App\Models\PortalExam;
use App\Models\PortalExamResult;
use App\Models\PortalResult;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Picks the few students used to confirm that an exam's results are visible.
 * A probe must be someone who plausibly sat the exam: for a regular exam the
 * students with the best CGPA so far; for a retake / improvement exam the
 * students with a failing or low grade (top students never appear in those).
 */
class ProbeSelector
{
    public function __construct(protected EligibleStudents $eligible) {}

    /**
     * @return Collection<int, Student>
     */
    public function select(PortalExam $exam, int $count): Collection
    {
        $students = $this->eligible->forExam($exam);

        if ($students->isEmpty()) {
            return $students;
        }

        $ids = $students->pluck('id')->all();

        $scores = $exam->kind === PortalExamKind::Regular
            ? PortalExamResult::query()->whereIn('student_id', $ids)->whereNotNull('cgpa')->orderByDesc('portal_exam_id')->get()
                ->unique('student_id')->mapWithKeys(fn (PortalExamResult $result): array => [$result->student_id => -1 * (float) $result->cgpa])
            : PortalResult::query()->whereIn('student_id', $ids)->current()->where('grade_point', '<=', 2.0)->get()
                ->groupBy('student_id')->map(fn (Collection $rows): float => (float) $rows->min('grade_point'));

        return $students
            ->sortBy(fn (Student $student): float => (float) ($scores[$student->id] ?? PHP_INT_MAX))
            ->values()
            ->take($count);
    }
}
