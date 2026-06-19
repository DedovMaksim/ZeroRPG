<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\InventoryController;

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

Route::get('/market', [MarketController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('market.index');
    
Route::post('/market/sell-all', [MarketController::class, 'sellAll'])
    ->middleware(['auth', 'verified'])
    ->name('market.sell-all');
    
Route::post('/market/sell', [MarketController::class, 'sell'])
    ->middleware(['auth', 'verified'])
    ->name('market.sell');
    
Route::get('/archive', [ArchiveController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('archive.index');
    
Route::get('/inventory', [InventoryController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('inventory');    

require __DIR__.'/auth.php';