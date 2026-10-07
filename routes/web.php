<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

/*
 * Seluruh rute di bawah ini dijaga Gate per kemampuan (NFR2). Matriks peran
 * berada pada config/sipasti.php.
 */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [AuthController::class, 'home'])->name('home');

    // FR7, papan perkara dan berkas perkara.
    Route::get('/board', [BoardController::class, 'index'])->middleware('can:case.view')->name('board');

    Route::get('/cases/create', [CaseController::class, 'create'])->middleware('can:case.register')->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->middleware('can:case.register')->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->middleware('can:case.view')->name('cases.show');

    Route::middleware('can:case.update')->group(function () {
        Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::post('/cases/{case}/stage', [CaseController::class, 'advance'])->name('cases.stage');
        Route::post('/cases/{case}/stall-causes', [CaseController::class, 'stallCause'])->name('cases.stall-causes');
        Route::post('/cases/{case}/mission-contacts', [CaseController::class, 'missionLetter'])->name('cases.mission-contacts');
        Route::post('/mission-contacts/{contact}/response', [CaseController::class, 'missionResponse'])->name('mission-contacts.response');
    });

    Route::post('/cases/{case}/status', [CaseController::class, 'changeStatus'])->middleware('can:status.change')->name('cases.status');
    Route::post('/cases/{case}/close', [CaseController::class, 'close'])->middleware('can:case.close')->name('cases.close');

    // FR10, penutupan peringatan menuntut alasan.
    Route::post('/alerts/{alert}/close', [AlertController::class, 'close'])->middleware('can:alert.review')->name('alerts.close');

    // Ambang sebagai data.
    Route::get('/indicators', [IndicatorController::class, 'index'])->middleware('can:indicators.manage')->name('indicators.index');
    Route::put('/indicators/{indicator}', [IndicatorController::class, 'update'])->middleware('can:indicators.manage')->name('indicators.update');
    Route::post('/indicators/run', [IndicatorController::class, 'run'])->middleware('can:indicators.run')->name('indicators.run');

    // FR8, laporan durasi tahap.
    Route::get('/reports/stage-durations', [ReportController::class, 'stageDurations'])->middleware('can:report.view')->name('reports.stage-durations');

    // NFR3, log audit.
    Route::get('/audit', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
});
