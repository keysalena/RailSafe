<?php

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Halaman 1: Dashboard Real-Time Monitoring (Operator & Admin)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', RoleMiddleware::class . ':admin,operator'])
    ->name('dashboard');

// Route login/logout bawaan (Breeze/Fortify/dll) kalau ada
if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}
