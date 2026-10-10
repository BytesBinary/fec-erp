<?php

namespace App\Http\Controllers;

use App\Enums\ClearanceStatus;
use App\Models\ClearanceRequest;
use App\Models\InstitutionSetting;
use App\Services\Clearance\HashChain;
use Illuminate\Contracts\View\View;

/**
 * Public verification page behind the QR code (spec §8.7). No login; shows the
 * minimum: student name, roll, program, request number, final status, approval
 * dates and whether the approval chain is intact.
 */
class ClearanceVerificationController extends Controller
{
    public function __invoke(string $code, HashChain $chain): View
    {
        $request = ClearanceRequest::query()
            ->with(['student.user', 'student.program', 'approvals.stage'])
            ->where('verify_code', $code)
            ->first();

        abort_if($request === null, 404);

        $integrity = $chain->verify($request);

        $valid = $integrity['intact'] && in_array($request->status, [ClearanceStatus::ReadyForCollection, ClearanceStatus::Printed, ClearanceStatus::Collected], true);

        return view('clearance.verify', [
            'institution' => InstitutionSetting::current(),
            'request' => $request,
            'integrity' => $integrity,
            'valid' => $valid,
            'approvals' => $request->approvals->where('decision', 'approved')->groupBy('stage_id')->map->last()->sortBy(fn ($approval) => $approval->stage->order)->values(),
        ]);
    }
}
