<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\Notifications\NotificationEvents;
use App\Services\ResultPortal\ResultPullService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Auth;

/**
 * Students are created through the model by the panel, MCP tools and the
 * assistant alike, so this one observer starts the result pull and sends the
 * "student added / changed" emails for all of them.
 */
class StudentObserver implements ShouldHandleEventsAfterCommit
{
    protected const WATCHED = ['department_id' => 'department', 'batch_id' => 'batch', 'current_semester' => 'semester', 'roll_number' => 'roll number', 'registration_number' => 'registration number', 'program_id' => 'program'];

    public function __construct(protected ResultPullService $pulls, protected NotificationEvents $events) {}

    public function created(Student $student): void
    {
        $student->loadMissing(['user', 'department', 'batch']);

        $this->events->emit('student.added', ['department' => $student->department?->code, 'batch' => (string) $student->batch?->display_name, 'link' => url('/')], 'student_added:'.$student->getKey(), $student->user, $student->department_id);
        $this->events->emit('student.added_admin_copy', ['student' => $student->user?->name, 'roll' => $student->roll_number, 'department' => $student->department?->code, 'batch' => (string) $student->batch?->display_name, 'link' => url('/students')], 'student_added_copy:'.$student->getKey(), null, $student->department_id);

        $this->pulls->queueFor($student, 'student_created', Auth::user());
    }

    public function updated(Student $student): void
    {
        $changed = collect(self::WATCHED)->filter(fn (string $label, string $column): bool => $student->wasChanged($column))->values();

        if ($changed->isNotEmpty()) {
            $this->events->emit('student.updated', ['fields' => $changed->implode(', '), 'link' => url('/')], 'student_updated:'.$student->getKey().':'.md5($changed->implode(',').now()->format('YmdHi')), $student->user, $student->department_id);
        }

        if ($student->wasChanged('registration_number')) {
            $this->pulls->queueFor($student, 'registration_changed', Auth::user());
        }
    }
}
