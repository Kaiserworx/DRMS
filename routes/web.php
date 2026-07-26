<?php

use App\Http\Controllers\ReceivingBoxController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function (): void {
    Route::get('/receiving-boxes/{qrToken}', [ReceivingBoxController::class, 'inventory'])
        ->where('qrToken', '[A-Za-z0-9]{64}')
        ->middleware('throttle:drms-receiving-box-lookups')
        ->name('receiving-boxes.inventory');
    Route::post('/receiving-boxes/{qrToken}/claims', [ReceivingBoxController::class, 'claim'])
        ->where('qrToken', '[A-Za-z0-9]{64}')
        ->middleware('throttle:drms-receiving-box-claims')
        ->name('receiving-boxes.claim');
    Route::get('/admin/receiving-boxes/{receivingBox}/label', [ReceivingBoxController::class, 'label'])
        ->name('receiving-boxes.label');
    Route::get('/admin/reports/export', ReportExportController::class)
        ->middleware('throttle:drms-report-exports')
        ->name('reports.export');
});
