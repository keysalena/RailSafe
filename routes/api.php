<?php

use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

// Dipakai oleh ESP32 (Node 2). Tanpa login, memakai token per perangkat.
Route::middleware(['device.token', 'throttle:120,1'])->group(function () {
    Route::post('/telemetry', [TelemetryController::class, 'store']);
    Route::post('/heartbeat', [TelemetryController::class, 'heartbeat']);
    Route::get('/config', [TelemetryController::class, 'config']);
});
