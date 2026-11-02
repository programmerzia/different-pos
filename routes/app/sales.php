<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\DueController;
use App\Http\Controllers\Sales\ExchangeController;
use App\Http\Controllers\Sales\ReturnController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SalePaymentController;
use App\Http\Controllers\Sales\SaleReceiptController;
use Illuminate\Support\Facades\Route;

/*
| Sales history, returns + exchange, dues and the print views (02_AppFlow §2 rows 30-35, 40-43, 55).
| Included from routes/app.php inside the session group (auth, tenant, branch). Sales, returns and
| payments are resolved by id inside the controllers, so the tenant + branch scopes decide the 404.
| The public receipt (/r/{token}) lives in routes/web.php.
*/

Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
Route::get('sales/{sale}', [SaleController::class, 'show'])->whereNumber('sale')->name('sales.show');
Route::get('sales/{sale}/receipt', [SaleReceiptController::class, 'sale'])->whereNumber('sale')->name('sales.receipt');
Route::post('sales/{sale}/void', [SaleController::class, 'void'])->whereNumber('sale')->name('sales.void');
Route::post('sales/{sale}/payments', [SalePaymentController::class, 'store'])->whereNumber('sale')->name('sales.payments.store');
Route::post('sales/{sale}/share', [SaleController::class, 'share'])->whereNumber('sale')->name('sales.share');

Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
Route::get('returns/create', [ReturnController::class, 'create'])->name('returns.create');
Route::post('returns', [ReturnController::class, 'store'])->name('returns.store');
Route::get('returns/{return}', [ReturnController::class, 'show'])->whereNumber('return')->name('returns.show');
Route::get('returns/{return}/receipt', [SaleReceiptController::class, 'saleReturn'])->whereNumber('return')->name('returns.receipt');

Route::get('exchanges/create', [ReturnController::class, 'createExchange'])->name('exchanges.create');
Route::post('exchanges', [ExchangeController::class, 'store'])->name('exchanges.store');

Route::get('dues', [DueController::class, 'index'])->name('dues.index');
Route::get('dues/{customer}', [DueController::class, 'show'])->name('dues.show');
Route::post('dues/{customer}/payments', [DueController::class, 'store'])->name('dues.payments.store');

Route::get('payments/{payment}/receipt', [SaleReceiptController::class, 'payment'])->whereNumber('payment')->name('payments.receipt');
