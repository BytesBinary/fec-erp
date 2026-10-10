<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Services\Notifications\NotificationEvents;
use App\Services\Profile\ProfileCompletionChecker;
use Illuminate\Console\Command;

/**
 * Daily scan for events that are not caused by a click but by the calendar:
 * library books due soon / overdue, students with an unfinished profile and
 * a spike of denied AI tool calls. Safe to run twice: every event is keyed by
 * what it is about and the day (or week).
 */
class ScanNotificationEvents extends Command
{
    protected $signature = 'notifications:scan';

    protected $description = 'Emit the time-based email events (due soon, overdue, profile reminders, denied tool calls)';

    public function handle(NotificationEvents $events, ProfileCompletionChecker $profiles): int
    {
        $count = 0;

        LibraryLoan::query()->whereNull('returned_on')->with('student.user')->each(function (LibraryLoan $loan) use ($events, &$count): void {
            if ($loan->due_on === null) {
                return;
            }

            $user = $loan->student->user;

            if ($loan->due_on->isFuture() && $loan->due_on->lte(today()->addDays(2))) {
                $events->emit('library.loan_due_soon', ['title' => $loan->book_title, 'due' => $loan->due_on->format('d M Y'), 'link' => url('/')], 'loan_due_soon:'.$loan->getKey(), $user, $loan->student->department_id);
                $count++;
            }

            if ($loan->due_on->isPast() && ! $loan->due_on->isToday()) {
                $events->emit('library.loan_overdue', ['title' => $loan->book_title, 'due' => $loan->due_on->format('d M Y'), 'link' => url('/')], 'loan_overdue:'.$loan->getKey().':'.now()->format('o-W'), $user, $loan->student->department_id);
                $count++;
            }
        });

        Student::query()->where('created_at', '<=', now()->subDays(3))->with(['user', 'profile'])->each(function (Student $student) use ($events, $profiles, &$count): void {
            if ($student->profile?->profile_completed_at !== null) {
                return;
            }

            $missing = count($profiles->problems($student));

            if ($missing > 0) {
                $events->emit('profile.incomplete_reminder', ['missing_count' => $missing, 'link' => url('/profile/complete')], 'profile_reminder:'.$student->getKey().':'.now()->format('o-W'), $student->user, $student->department_id);
                $count++;
            }
        });

        $denied = AuditLog::query()->where('action', 'mcp.tool_call')->where('created_at', '>=', now()->subDay())->get(['after'])->filter(fn (AuditLog $log): bool => ($log->after['outcome'] ?? null) === 'denied')->count();

        if ($denied >= (int) config('notifications.denied_spike_threshold', 100)) {
            $events->emit('system.mcp_denied_spike', ['count' => $denied, 'link' => url('/security/mcp-integrations')], 'mcp_denied:'.now()->format('Ymd'));
            $count++;
        }

        $this->components->info("{$count} event(s) emitted.");

        return self::SUCCESS;
    }
}
