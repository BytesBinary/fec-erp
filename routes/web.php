<?php

use App\Http\Controllers\ClearancePrintController;
use App\Http\Controllers\ClearanceVerificationController;
use App\Http\Controllers\ResultSheetController;
use Illuminate\Support\Facades\Route;

Route::middleware('erp.secure')->group(function (): void {
    Route::get('/results/print', ResultSheetController::class)->name('results.print');
    Route::get('/clearance/{clearanceRequest}/print', [ClearancePrintController::class, 'show'])->whereNumber('clearanceRequest')->name('clearance.print');
    Route::get('/clearance/{clearanceRequest}/pdf', [ClearancePrintController::class, 'pdf'])->whereNumber('clearanceRequest')->name('clearance.pdf');
});

Route::get('/verify/clearance/{code}', ClearanceVerificationController::class)
    ->where('code', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:60,1')
    ->name('clearance.verify');
