<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\CentralAiController;
use App\Http\Controllers\ConstructionController;
use App\Http\Controllers\WarehouseController;

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
    
Route::get('/central-ai', [CentralAiController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('central-ai');
    
Route::post('/construction/requirements/{requirement}/transfer', [ConstructionController::class, 'transfer'])
    ->middleware(['auth', 'verified'])
    ->name('construction.transfer');
    
Route::post('/warehouse/deposit/{inventory}', [WarehouseController::class, 'deposit'])
    ->middleware(['auth', 'verified'])
    ->name('warehouse.deposit');
    
Route::post('/warehouse/withdraw/{warehouseInventory}', [WarehouseController::class, 'withdraw'])
    ->middleware(['auth', 'verified'])
    ->name('warehouse.withdraw');    

require __DIR__.'/auth.php';