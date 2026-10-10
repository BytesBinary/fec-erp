<?php

namespace App\Services\Clearance;

use App\Enums\ClearanceStatus;
use App\Enums\RoleKey;
use App\Filament\Pages\Clearance\ClearanceDesk;
use App\Filament\Pages\Clearance\PendingApprovals;
use App\Models\ClearanceRequest;
use App\Models\User;
use App\Notifications\ClearanceNotification;

/**
 * Scheduled reminders (spec §8.8): approvers when a request waits too long,
 * super admins on escalation, and a daily digest for the administration office.
 */
class ClearanceReminderService
{
    public function __construct(protected ApproverResolver $approvers) {}

    /**
     * @return array{reminded: int, escalated: int, digest: int}
     */
    public function run(): array
    {
        $remindAfter = (int) config('clearance.remind_after_days');
        $escalateAfter = (int) config('clearance.escalate_after_days');
        $reminded = 0;
        $escalated = 0;

        ClearanceRequest::query()
            ->where('status', ClearanceStatus::Pending->value)
            ->where('updated_at', '<=', now()->subDays($remindAfter))
            ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays($remindAfter)))
            ->with(['student.user', 'currentStage.approverRole'])
            ->each(function (ClearanceRequest $request) use ($escalateAfter, &$reminded, &$escalated): void {
                $stage = $request->currentStage;

                if ($stage === null) {
                    return;
                }

                $days = (int) $request->updated_at->diffInDays(now());

                $this->approvers->approversFor($request, $stage)->each(fn (User $approver) => $approver->notify(new ClearanceNotification(
                    __('erp.clearance.remind.title'),
                    __('erp.clearance.remind.body', ['student' => $request->student->user->name, 'no' => $request->request_no, 'stage' => $stage->label, 'days' => $days]),
                    PendingApprovals::getUrl(),
                    'warning',
                )));

                if ($days >= $escalateAfter) {
                    User::role(RoleKey::SuperAdmin->value)->where('is_active', true)->get()->each(fn (User $admin) => $admin->notify(new ClearanceNotification(
                        __('erp.clearance.remind.escalation_title'),
                        __('erp.clearance.remind.escalation_body', ['student' => $request->student->user->name, 'no' => $request->request_no, 'stage' => $stage->label, 'days' => $days]),
                        null,
                        'danger',
                    )));
                    $escalated++;
                }

                $request->forceFill(['last_reminded_at' => now()])->save();
                $reminded++;
            });

        return ['reminded' => $reminded, 'escalated' => $escalated, 'digest' => $this->digest()];
    }

    /**
     * Tells the administration office how many clearances are ready for collection.
     */
    public function digest(): int
    {
        $ready = ClearanceRequest::query()->where('status', ClearanceStatus::ReadyForCollection->value)->count();

        if ($ready === 0) {
            return 0;
        }

        $office = User::role(RoleKey::AdminOffice->value)->where('is_active', true)->get();

        $office->each(fn (User $user) => $user->notify(new ClearanceNotification(
            __('erp.clearance.remind.digest_title'),
            __('erp.clearance.remind.digest_body', ['count' => $ready]),
            ClearanceDesk::getUrl(),
            'info',
        )));

        return $office->count();
    }
}
