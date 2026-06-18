<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpeditionController;

Route::view('/', 'welcome');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('/expeditions/start/{location}', [ExpeditionController::class, 'start'])
    ->middleware(['auth', 'verified'])
    ->name('expeditions.start');

require __DIR__.'/auth.php';