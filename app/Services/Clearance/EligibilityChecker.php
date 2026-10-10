<?php

namespace App\Services\Clearance;

use App\Enums\ClearanceStatus;
use App\Enums\ResultStatus;
use App\Models\ClearanceRequest;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Student;
use App\Services\Profile\ProfileCompletionChecker;
use App\Services\Results\ResultService;

/**
 * Who may apply for clearance (spec §8.3, docs/DECISIONS.md D-018): complete
 * profile, active account, no other active request, required credits earned
 * and no result still awaiting publication for a course that has no published
 * pass yet.
 */
class EligibilityChecker
{
    public function __construct(
        protected ProfileCompletionChecker $profile,
        protected ResultService $results,
    ) {}

    public function check(Student $student, ?int $ignoreRequestId = null): EligibilityResult
    {
        $student->loadMissing(['user', 'program']);
        $reasons = [];

        if (! $this->profile->isComplete($student)) {
            $reasons[] = $this->reason('profile_incomplete', __('erp.clearance.reasons.profile_incomplete'));
        }

        if ($student->user?->is_active === false || $student->trashed()) {
            $reasons[] = $this->reason('account_inactive', __('erp.clearance.reasons.account_inactive'));
        }

        $active = ClearanceRequest::query()
            ->where('student_id', $student->getKey())
            ->whereNotIn('status', [ClearanceStatus::Collected->value, ClearanceStatus::Cancelled->value])
            ->when($ignoreRequestId !== null, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->first();

        if ($active !== null) {
            $reasons[] = $this->reason('active_request_exists', __('erp.clearance.reasons.active_request_exists', ['no' => $active->request_no]));
        }

        $required = (float) ($student->program?->required_credits ?? 0);
        $earned = $this->results->publishedSummary($student)->earnedCredits;

        if ($required > 0 && $earned + 1e-9 < $required) {
            $reasons[] = $this->reason('credits_missing', __('erp.clearance.reasons.credits_missing', ['earned' => $this->fmt($earned), 'required' => $this->fmt($required)]));
        }

        $waiting = $this->coursesAwaitingPublication($student);

        if ($waiting !== []) {
            $reasons[] = $this->reason('results_unpublished', __('erp.clearance.reasons.results_unpublished', ['courses' => implode(', ', $waiting)]));
        }

        return new EligibilityResult($reasons);
    }

    /**
     * Codes of courses with an unpublished result and no published pass.
     *
     * @return list<string>
     */
    protected function coursesAwaitingPublication(Student $student): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->getKey())
            ->where('status', 'enrolled')
            ->with(['result', 'offering.course'])
            ->get();

        $passed = $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->result?->status === ResultStatus::Published && (float) $enrollment->result->grade_point > (float) config('grading.pass_above'))
            ->pluck('offering.course_id')
            ->all();

        return $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->result instanceof Result && $enrollment->result->status !== ResultStatus::Published && ! in_array($enrollment->offering->course_id, $passed, true))
            ->map(fn (Enrollment $enrollment): string => $enrollment->offering->course->code)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{code: string, message: string}
     */
    protected function reason(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    protected function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
