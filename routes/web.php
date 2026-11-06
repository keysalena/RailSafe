<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CrossingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Superadmin dan operator
Route::middleware(['auth', 'role:superadmin,operator'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/export', [EventController::class, 'export'])->name('events.export');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event}/notes', [EventController::class, 'storeNote'])->name('events.note');
});

// Khusus superadmin
Route::middleware(['auth', 'role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    Route::get('/crossings', [CrossingController::class, 'index'])->name('crossings.index');
    Route::post('/crossings', [CrossingController::class, 'storeCrossing'])->name('crossings.store');
    Route::post('/devices', [CrossingController::class, 'storeDevice'])->name('devices.store');
    Route::post('/devices/{device}/token', [CrossingController::class, 'rotateToken'])->name('devices.token');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
});
