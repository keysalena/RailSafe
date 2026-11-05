<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Middleware\DeviceKey;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RailSafe API
|--------------------------------------------------------------------------
| 1) /api/device/*    -> dipanggil ESP32 (header X-Device-Key)
| 2) /api/dashboard/* -> dipanggil browser (session login, role admin/operator)
*/

// ---- Perangkat (Node 2 / gateway) ----
Route::prefix('device')->middleware(DeviceKey::class)->group(function () {
    Route::post('telemetry', [DeviceController::class, 'telemetry']);
    Route::post('alert', [DeviceController::class, 'alert']);
    Route::post('gate-status', [DeviceController::class, 'gateStatus']);
    Route::get('commands/pending', [DeviceController::class, 'pendingCommands']);
    Route::post('commands/{command}/ack', [DeviceController::class, 'ackCommand']);
});

// ---- Dashboard (Halaman 1: Real-Time Monitoring) ----
Route::prefix('dashboard')
    ->middleware(['web', 'auth', RoleMiddleware::class . ':admin,operator'])
    ->group(function () {
        Route::get('live', [DashboardController::class, 'live']);
        Route::post('alerts/{alert}/acknowledge', [DashboardController::class, 'acknowledge']);
        Route::post('gate/command', [DashboardController::class, 'gateCommand']);
    });
