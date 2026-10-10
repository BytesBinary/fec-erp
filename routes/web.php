<?php

use App\Http\Controllers\Assistant\ChatController;
use App\Http\Controllers\ClearancePrintController;
use App\Http\Controllers\ClearanceVerificationController;
use App\Http\Controllers\ResultSheetController;
use Illuminate\Support\Facades\Route;

Route::middleware('erp.secure')->group(function (): void {
    Route::get('/results/print', ResultSheetController::class)->name('results.print');
    Route::get('/clearance/{clearanceRequest}/print', [ClearancePrintController::class, 'show'])->whereNumber('clearanceRequest')->name('clearance.print');
    Route::prefix('api/assistant')->middleware('throttle:assistant')->name('assistant.')->group(function (): void {
        Route::post('chat', [ChatController::class, 'chat'])->name('chat');
        Route::get('history', [ChatController::class, 'history'])->name('history');
        Route::delete('history', [ChatController::class, 'clear'])->name('clear');
        Route::post('actions/{action}/confirm', [ChatController::class, 'confirm'])->whereNumber('action')->name('confirm');
        Route::post('actions/{action}/cancel', [ChatController::class, 'cancel'])->whereNumber('action')->name('cancel');
    });
    Route::get('/clearance/{clearanceRequest}/pdf', [ClearancePrintController::class, 'pdf'])->whereNumber('clearanceRequest')->name('clearance.pdf');
});

Route::get('/verify/clearance/{code}', ClearanceVerificationController::class)
    ->where('code', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:60,1')
    ->name('clearance.verify');
