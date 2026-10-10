<?php

namespace App\Jobs;

use App\Models\HallResidency;
use App\Models\Notice;
use App\Models\User;
use App\Services\Notifications\NotificationEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Emits `notice.published` for everyone the notice is addressed to. Skipped
 * entirely while that email event is switched off.
 */
class EmitNoticeNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $noticeId) {}

    public function handle(NotificationEvents $events): void
    {
        $notice = Notice::query()->find($this->noticeId);

        if ($notice === null) {
            return;
        }

        $users = User::query()->where('is_active', true)->when($notice->audience === 'department' && $notice->department_id !== null, fn ($query) => $query->where(
            fn ($inner) => $inner->whereHas('student', fn ($student) => $student->where('department_id', $notice->department_id))
                ->orWhereHas('teacher', fn ($teacher) => $teacher->where('department_id', $notice->department_id)),
        ))->when($notice->audience === 'hall' && $notice->hall_id !== null, fn ($query) => $query->whereHas('student', fn ($student) => $student->whereIn('id', HallResidency::query()->current()->where('hall_id', $notice->hall_id)->pluck('student_id'))));

        $users->chunkById(200, function ($chunk) use ($events, $notice): void {
            foreach ($chunk as $user) {
                $events->emit('notice.published', ['title' => $notice->title, 'link' => url('/notices')], 'notice:'.$notice->getKey().':'.$user->getKey(), $user);
            }
        });
    }
}
