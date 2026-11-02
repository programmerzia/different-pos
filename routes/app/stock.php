<?php

declare(strict_types=1);

use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Stock\AdjustmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Vendors\VendorController;
use Illuminate\Support\Facades\Route;

/* Stock, adjustments, vendors and purchases. Loaded inside the branch-selected group of routes/app.php. */

Route::get('stock', [StockController::class, 'index'])->name('stock.index');
Route::get('stock/{product}/movements', [StockController::class, 'movements'])->name('stock.movements');

Route::get('adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
Route::get('adjustments/create', [AdjustmentController::class, 'create'])->name('adjustments.create');
Route::get('adjustments/lookup', [AdjustmentController::class, 'lookup'])->name('adjustments.lookup');
Route::post('adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');

Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
Route::get('vendors/create', [VendorController::class, 'create'])->name('vendors.create');
Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
Route::get('vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
Route::post('vendors/{vendor}/payments', [VendorController::class, 'pay'])->name('vendors.pay');

Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
Route::get('purchases/lookup', [PurchaseController::class, 'lookup'])->name('purchases.lookup');
Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
Route::post('purchases/{purchase}/payments', [PurchaseController::class, 'pay'])->name('purchases.pay');
