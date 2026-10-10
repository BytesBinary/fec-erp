<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\ResultPortal\ResultPullService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Auth;

/**
 * Students are created through the model by the panel, MCP tools and the
 * assistant alike, so this one observer starts the result pull for all of them.
 */
class StudentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(protected ResultPullService $pulls) {}

    public function created(Student $student): void
    {
        $this->pulls->queueFor($student, 'student_created', Auth::user());
    }

    public function updated(Student $student): void
    {
        if ($student->wasChanged('registration_number')) {
            $this->pulls->queueFor($student, 'registration_changed', Auth::user());
        }
    }
}
