<?php

namespace App\Observers;

use App\Jobs\EmitNoticeNotifications;
use App\Models\Notice;

/**
 * A published notice becomes one email event per person in its audience, made
 * in the background (an audience can be the whole institution).
 */
class NoticeObserver
{
    public function created(Notice $notice): void
    {
        if ($notice->published_at !== null && $notice->published_at->isPast()) {
            EmitNoticeNotifications::dispatch($notice->getKey())->afterCommit();
        }
    }
}
