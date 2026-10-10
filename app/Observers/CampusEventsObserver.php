<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\LibraryLoan;
use App\Models\Semester;
use App\Services\Notifications\NotificationEvents;
use Illuminate\Database\Eloquent\Model;

/**
 * Emails for enrollment, hall and library changes. Registered once for each
 * model; every channel (panel, MCP, assistant) writes through the models, so
 * all of them are covered.
 */
class CampusEventsObserver
{
    public function __construct(protected NotificationEvents $events) {}

    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Enrollment => $this->enrollment($model, 'enrollment.enrolled'),
            $model instanceof HallResidency => $this->events->emit('hall.assigned', ['hall' => $model->hall?->name, 'room' => $model->room ?: 'not set', 'link' => url('/')], 'hall_assigned:'.$model->getKey(), $model->student->user, $model->student->department_id, $model->hall_id),
            $model instanceof HallDue => $this->events->emit('hall.due_recorded', ['description' => $model->description, 'amount' => number_format((float) $model->amount, 2), 'link' => url('/clearance/apply')], 'due_recorded:'.$model->getKey(), $model->student->user, $model->student->department_id, $model->hall_id),
            $model instanceof LibraryLoan => $this->events->emit('library.loan_issued', ['title' => $model->book_title, 'due' => $model->due_on?->format('d M Y'), 'link' => url('/')], 'loan_issued:'.$model->getKey(), $model->student->user, $model->student->department_id),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        match (true) {
            $model instanceof Enrollment && $model->wasChanged('status') && $model->status === 'dropped' => $this->enrollment($model, 'enrollment.dropped'),
            $model instanceof HallResidency && $model->wasChanged('ended_on') && $model->ended_on !== null => $this->events->emit('hall.vacated', ['hall' => $model->hall?->name, 'link' => url('/')], 'hall_vacated:'.$model->getKey(), $model->student->user, $model->student->department_id, $model->hall_id),
            $model instanceof HallDue && $model->wasChanged('settled_at') && $model->settled_at !== null => $this->events->emit('hall.due_settled', ['description' => $model->description, 'amount' => number_format((float) $model->amount, 2), 'link' => url('/')], 'due_settled:'.$model->getKey(), $model->student->user, $model->student->department_id, $model->hall_id),
            $model instanceof LibraryLoan => $this->loan($model),
            $model instanceof Semester && $model->wasChanged('is_active') && $model->is_active => $this->events->emit('semester.activated', ['semester' => $model->name], 'semester_active:'.$model->getKey().':'.now()->format('YmdHi')),
            default => null,
        };
    }

    protected function enrollment(Enrollment $enrollment, string $event): void
    {
        $enrollment->loadMissing(['student.user', 'offering.course', 'offering.semester']);

        $this->events->emit($event, [
            'course' => $enrollment->offering->course->code.' '.$enrollment->offering->course->name,
            'semester' => (string) $enrollment->offering->semester?->name,
            'link' => url('/'),
        ], $event.':'.$enrollment->getKey().':'.$enrollment->status, $enrollment->student->user, $enrollment->student->department_id);
    }

    protected function loan(LibraryLoan $loan): void
    {
        if ($loan->wasChanged('returned_on') && $loan->returned_on !== null) {
            $this->events->emit('library.loan_returned', ['title' => $loan->book_title, 'link' => url('/')], 'loan_returned:'.$loan->getKey(), $loan->student->user, $loan->student->department_id);

            if ((float) $loan->fine_amount > 0) {
                $this->events->emit('library.fine_recorded', ['title' => $loan->book_title, 'amount' => number_format((float) $loan->fine_amount, 2), 'link' => url('/clearance/apply')], 'fine_recorded:'.$loan->getKey(), $loan->student->user, $loan->student->department_id);
            }
        }

        if ($loan->wasChanged('fine_settled_at') && $loan->fine_settled_at !== null) {
            $this->events->emit('library.fine_settled', ['title' => $loan->book_title, 'amount' => number_format((float) $loan->fine_amount, 2), 'link' => url('/')], 'fine_settled:'.$loan->getKey(), $loan->student->user, $loan->student->department_id);
        }
    }
}
