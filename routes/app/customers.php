<?php

declare(strict_types=1);

use App\Http\Controllers\Cards\MembershipCardController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerStatementController;
use Illuminate\Support\Facades\Route;

/*
| Customers, statement and membership cards (02_AppFlow §2 rows 50-60). Included from routes/app.php
| inside the session group (auth, tenant, branch). Customers bind by uuid in the controllers so the
| tenant scope decides the 404, never the middleware order.
*/

Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
Route::get('customers/check-phone', [CustomerController::class, 'checkPhone'])->name('customers.check-phone');
Route::get('customers/statement', [CustomerStatementController::class, 'index'])->name('customers.statement.index');

Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
Route::post('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('customers.deactivate');
Route::post('customers/{customer}/merge', [CustomerController::class, 'merge'])->name('customers.merge');

Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])->name('customers.statement');
Route::post('customers/{customer}/statement/sms', [CustomerStatementController::class, 'sms'])->name('customers.statement.sms');

Route::get('membership-cards', [MembershipCardController::class, 'index'])->name('cards.index');
Route::post('membership-cards/issue', [MembershipCardController::class, 'issue'])->name('cards.issue');
Route::get('membership-cards/print', [MembershipCardController::class, 'print'])->name('cards.print');
Route::get('membership-cards/export.csv', [MembershipCardController::class, 'export'])->name('cards.export');
Route::post('membership-cards/printed', [MembershipCardController::class, 'printed'])->name('cards.printed');
Route::post('membership-cards/{card}/lost', [MembershipCardController::class, 'lost'])->name('cards.lost')->whereNumber('card');
Route::post('membership-cards/{card}/reissue', [MembershipCardController::class, 'reissue'])->name('cards.reissue')->whereNumber('card');
