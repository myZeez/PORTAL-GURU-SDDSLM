<?php

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('avatars/{user}', [AvatarController::class, 'show'])->name('avatars.show');
Route::middleware('auth')->get('media/avatars/{file}', [AvatarController::class, 'file'])->where('file', '[A-Za-z0-9._-]+');

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('attendances.pdf', [ReportPdfController::class, 'attendances'])->name('attendances.pdf');
    Route::get('journals.pdf', [ReportPdfController::class, 'journals'])->name('journals.pdf');
    Route::get('monev.pdf', [ReportPdfController::class, 'monev'])->name('monev.pdf');
    Route::get('monitoring.pdf', [ReportPdfController::class, 'monitoring'])->name('monitoring.pdf');
    Route::get('performance.pdf', [ReportPdfController::class, 'performance'])->name('performance.pdf');
    Route::get('substitutions.pdf', [ReportPdfController::class, 'substitutions'])->name('substitutions.pdf');
});
