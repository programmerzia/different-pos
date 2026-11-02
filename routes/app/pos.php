<?php

declare(strict_types=1);

use App\Http\Controllers\Pos\LookupController;
use App\Http\Controllers\Pos\ManagerApprovalController;
use App\Http\Controllers\Pos\RegisterController;
use App\Http\Controllers\Pos\SaleController;
use Illuminate\Support\Facades\Route;

/*
| Online register (Inertia page + its JSON). Included from routes/app.php inside the session group
| (auth, tenant, branch). Every controller authorizes pos.sell; customers bind by uuid in the controller.
*/

Route::get('pos', RegisterController::class)->name('pos.register');
Route::post('pos/sales', [SaleController::class, 'store'])->name('pos.sales.store');

Route::prefix('api/pos')->as('pos.')->group(function (): void {
    Route::get('products', [LookupController::class, 'products'])->name('products');
    Route::get('customers', [LookupController::class, 'customers'])->name('customers');
    Route::get('customers/{customer}', [LookupController::class, 'customer'])->name('customers.show');
    Route::get('today', [LookupController::class, 'today'])->name('today');
    Route::post('authorize', ManagerApprovalController::class)->middleware('throttle:10,1')->name('authorize');
});
