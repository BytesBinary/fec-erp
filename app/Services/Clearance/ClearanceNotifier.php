<?php

namespace App\Services\Clearance;

use App\Filament\Pages\Clearance\MyClearance;
use App\Filament\Pages\Clearance\PendingApprovals;
use App\Models\ClearanceRequest;
use App\Models\ClearanceStage;
use App\Models\User;
use App\Notifications\ClearanceNotification;

/**
 * In-app (and pluggable e-mail/SMS) notifications for the clearance
 * lifecycle (spec §8.8).
 */
class ClearanceNotifier
{
    public function __construct(protected ApproverResolver $approvers) {}

    public function submitted(ClearanceRequest $request, bool $resubmitted = false): void
    {
        $student = $request->student->user;

        $student?->notify(new ClearanceNotification(
            $resubmitted ? __('erp.clearance.notify.resubmitted_title') : __('erp.clearance.notify.submitted_title'),
            __('erp.clearance.notify.submitted_body', ['no' => $request->request_no]),
            MyClearance::getUrl(),
            'info',
            $resubmitted ? 'clearance.resubmitted' : 'clearance.submitted',
        ));

        $this->notifyCurrentApprovers($request);
    }

    public function notifyCurrentApprovers(ClearanceRequest $request): void
    {
        $stage = $request->currentStage;

        if ($stage === null) {
            return;
        }

        $this->approvers->approversFor($request, $stage)->each(fn (User $approver) => $approver->notify(new ClearanceNotification(
            __('erp.clearance.notify.waiting_title'),
            __('erp.clearance.notify.waiting_body', ['no' => $request->request_no, 'student' => $request->student->user?->name, 'stage' => $stage->label]),
            PendingApprovals::getUrl(),
            'warning',
            'clearance.waiting',
        )));
    }

    public function approved(ClearanceRequest $request, ClearanceStage $stage): void
    {
        $request->student->user?->notify(new ClearanceNotification(
            __('erp.clearance.notify.approved_title', ['stage' => $stage->label]),
            __('erp.clearance.notify.approved_body', ['no' => $request->request_no, 'stage' => $stage->label]),
            MyClearance::getUrl(),
            'success', 'clearance.approved'
        ));

        $this->notifyCurrentApprovers($request);
    }

    public function rejected(ClearanceRequest $request, ClearanceStage $stage, string $reason): void
    {
        $request->student->user?->notify(new ClearanceNotification(
            __('erp.clearance.notify.rejected_title', ['stage' => $stage->label]),
            __('erp.clearance.notify.rejected_body', ['no' => $request->request_no, 'reason' => $reason]),
            MyClearance::getUrl(),
            'danger', 'clearance.rejected'
        ));
    }

    public function ready(ClearanceRequest $request): void
    {
        $request->student->user?->notify(new ClearanceNotification(
            __('erp.clearance.notify.ready_title'),
            __('erp.clearance.ready_message'),
            MyClearance::getUrl(),
            'success', 'clearance.ready_for_collection'
        ));
    }

    public function collected(ClearanceRequest $request): void
    {
        $request->student->user?->notify(new ClearanceNotification(
            __('erp.clearance.notify.collected_title'),
            __('erp.clearance.notify.collected_body', ['no' => $request->request_no]),
            MyClearance::getUrl(),
            'success', 'clearance.collected'
        ));
    }
}
