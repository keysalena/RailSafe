<?php

use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

// Dipakai oleh ESP32 (Node 2). Lihat config/railsafe.php untuk mode token.
Route::middleware(['device.token', 'throttle:120,1'])->group(function () {
    Route::post('/alert', [TelemetryController::class, 'alert']);          // awal hitung mundur petugas
    Route::post('/gate-log', [TelemetryController::class, 'gateLog']);     // dipakai firmware Node2.ino saat ini
    Route::post('/telemetry', [TelemetryController::class, 'store']);      // format lengkap (alur fail-safe proposal)
    Route::post('/heartbeat', [TelemetryController::class, 'heartbeat']);  // opsional
    Route::get('/config', [TelemetryController::class, 'config']);
});
