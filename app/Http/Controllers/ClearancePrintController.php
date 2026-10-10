<?php

namespace App\Http\Controllers;

use App\Models\ClearanceRequest;
use App\Models\User;
use App\Services\Clearance\ClearancePrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Print view and PDF of a finished clearance for the administration office.
 * Viewing never changes state; printing is recorded by the desk action
 * (`ClearanceService::recordPrint`), which then opens `?print={id}`.
 */
class ClearancePrintController extends Controller
{
    public function show(Request $request, ClearanceRequest $clearanceRequest, ClearancePrintService $prints): View
    {
        return view('clearance.print', $this->data($request, $clearanceRequest, $prints) + ['autoprint' => $request->boolean('autoprint'), 'pdfMode' => false]);
    }

    public function pdf(Request $request, ClearanceRequest $clearanceRequest, ClearancePrintService $prints): Response
    {
        $data = $this->data($request, $clearanceRequest, $prints) + ['autoprint' => false, 'pdfMode' => true];

        return Pdf::loadView('clearance.print', $data)->setPaper('a4')->stream("clearance-{$clearanceRequest->request_no}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request, ClearanceRequest $clearanceRequest, ClearancePrintService $prints): array
    {
        $user = $request->user();
        assert($user instanceof User);

        $print = $request->integer('print') > 0 ? $prints->printOf($user, $clearanceRequest, $request->integer('print')) : null;

        return $prints->documentData($user, $clearanceRequest, $print);
    }
}
