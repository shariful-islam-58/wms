<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Middleware\EnsureWmsRole;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', EnsureWmsRole::class])->group(function (): void {
    Route::apiResource('products', ProductController::class);

    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show']);
    Route::get('warehouses/{warehouse}/locations', [LocationController::class, 'index']);
    Route::get('locations/{location}', [LocationController::class, 'show']);

    Route::get('inventory', [InventoryController::class, 'index']);
    Route::post('inventory/receive', [InventoryController::class, 'receive']);
    Route::post('inventory/transfer', [InventoryController::class, 'transfer']);
    Route::post('inventory/dispatch', [InventoryController::class, 'dispatchStock']);

    Route::get('stock-movements', [StockMovementController::class, 'index']);
});

Route::middleware(['auth:sanctum', EnsureWmsRole::class.':admin'])->group(function (): void {
    Route::post('warehouses', [WarehouseController::class, 'store']);
    Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

    Route::post('warehouses/{warehouse}/locations', [LocationController::class, 'store']);
    Route::put('locations/{location}', [LocationController::class, 'update']);
    Route::delete('locations/{location}', [LocationController::class, 'destroy']);
});
