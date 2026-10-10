<?php

use App\Http\Controllers\ResultSheetController;
use Illuminate\Support\Facades\Route;

Route::middleware('erp.secure')->group(function (): void {
    Route::get('/results/print', ResultSheetController::class)->name('results.print');
});
