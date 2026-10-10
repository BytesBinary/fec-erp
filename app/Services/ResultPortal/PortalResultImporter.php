<?php

namespace App\Services\ResultPortal;

use App\Enums\PortalChangeType;
use App\Models\PortalExamResult;
use App\Models\PortalResult;
use App\Models\ResultPull;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores parsed portal rows and keeps, per course, exactly one current grade.
 * Older attempts stay as history; a newer attempt (retake / improvement exam)
 * replaces the current grade and is marked improved / retake / declined.
 * Importing the same rows again changes nothing.
 */
class PortalResultImporter
{
    /**
     * @param  list<PortalResultRow>  $rows
     * @param  list<PortalExamOutcome>  $outcomes
     * @return array{found: int, changed: int, grade_changes: Collection<int, PortalResult>}
     */
    public function import(Student $student, ResultPull $pull, array $rows, array $outcomes = []): array
    {
        return DB::transaction(function () use ($student, $pull, $rows, $outcomes): array {
            foreach ($outcomes as $outcome) {
                $this->saveExamResult($student, $pull, $outcome);
            }

            $changed = 0;
            $touchedIds = [];
            $touchedCourses = [];

            foreach ($rows as $row) {
                $existing = PortalResult::query()->firstWhere([
                    'student_id' => $student->getKey(),
                    'portal_exam_id' => $row->exam->id,
                    'course_code' => $row->courseCode,
                ]);

                $attributes = [
                    'result_pull_id' => $pull->getKey(),
                    'exam_title' => $row->exam->title,
                    'exam_kind' => $row->exam->kind,
                    'course_title' => $row->courseTitle,
                    'credits' => $row->credits,
                    'letter' => $row->letter,
                    'grade_point' => $row->gradePoint,
                    'fetched_at' => now(),
                ];

                if ($existing === null) {
                    $created = PortalResult::query()->create([...$attributes, 'student_id' => $student->getKey(), 'portal_exam_id' => $row->exam->id, 'course_code' => $row->courseCode]);
                    $touchedIds[] = $created->getKey();
                    $changed++;
                } elseif ($existing->letter !== $row->letter || $existing->grade_point !== $row->gradePoint) {
                    $existing->update($attributes);
                    $touchedIds[] = $existing->getKey();
                    $changed++;
                } else {
                    $existing->update(['fetched_at' => now(), 'result_pull_id' => $pull->getKey()]);
                }

                $touchedCourses[$row->courseCode] = true;
            }

            foreach (array_keys($touchedCourses) as $courseCode) {
                $this->settle($student, $courseCode);
            }

            $gradeChanges = PortalResult::query()->whereIn('id', $touchedIds)->where('is_current', true)->whereNotNull('change_type')->get();

            return ['found' => count($rows), 'changed' => $changed, 'grade_changes' => $gradeChanges];
        });
    }

    /**
     * Keeps what the portal said about the student in one exam (roll, outcome,
     * GPA, CGPA, backlog) and the raw page on the private disk, so a parser
     * fix can re-read it without asking the portal again.
     */
    protected function saveExamResult(Student $student, ResultPull $pull, PortalExamOutcome $outcome): void
    {
        $page = $outcome->page;
        $path = "portal-pages/{$student->getKey()}/{$outcome->exam->id}.html";

        Storage::disk('local')->put($path, $outcome->rawHtml);

        PortalExamResult::query()->updateOrCreate(
            ['student_id' => $student->getKey(), 'portal_exam_id' => $outcome->exam->id],
            [
                'result_pull_id' => $pull->getKey(),
                'exam_title' => $outcome->exam->title,
                'exam_kind' => $outcome->exam->kind,
                'exam_year' => $outcome->exam->examYear ?? (isset($page->meta['Exam Year']) ? (int) $page->meta['Exam Year'] : null),
                'exam_roll' => $page->meta['Exam Roll'] ?? null,
                'class_roll' => $page->meta['Class Roll'] ?? null,
                'published_on' => $this->date($page->meta['Result Publication Date'] ?? null),
                'outcome' => $page->outcome,
                'gpa' => $page->gpa,
                'cgpa' => $page->cgpa,
                'backlog_codes' => $page->backlog,
                'raw_page_path' => $path,
                'raw_page_hash' => hash('sha256', $outcome->rawHtml),
                'fetched_at' => now(),
            ],
        );
    }

    protected function date(?string $value): ?string
    {
        return $value !== null && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1 ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    }

    /**
     * Recomputes which attempt is current and how each later attempt related
     * to the grade it met. Attempts are ordered by portal exam id (ids grow
     * with time).
     */
    protected function settle(Student $student, string $courseCode): void
    {
        /** @var Collection<int, PortalResult> $attempts */
        $attempts = PortalResult::query()
            ->where('student_id', $student->getKey())
            ->where('course_code', $courseCode)
            ->orderBy('portal_exam_id')
            ->get();

        $current = null;

        foreach ($attempts as $attempt) {
            if ($current === null) {
                $attempt->forceFill(['change_type' => null, 'previous_letter' => null, 'previous_grade_point' => null]);
                $current = $attempt;

                continue;
            }

            $attempt->forceFill([
                'change_type' => $this->classify($attempt, $current),
                'previous_letter' => $current->letter,
                'previous_grade_point' => $current->grade_point,
            ]);

            if (config('result_portal.replace_policy') === 'better' && (float) $attempt->grade_point < (float) $current->grade_point) {
                continue;
            }

            $current = $attempt;
        }

        foreach ($attempts as $attempt) {
            $attempt->is_current = $attempt->is($current);
            $attempt->save();
        }
    }

    protected function classify(PortalResult $new, PortalResult $previous): PortalChangeType
    {
        $newPoint = (float) $new->grade_point;
        $oldPoint = (float) $previous->grade_point;

        return match (true) {
            $oldPoint <= 0.0 && $newPoint > 0.0 => PortalChangeType::Retake,
            $newPoint > $oldPoint => PortalChangeType::Improved,
            $newPoint < $oldPoint => PortalChangeType::Declined,
            default => PortalChangeType::Same,
        };
    }
}
