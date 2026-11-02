<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\PublicReceiptController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Shared receipt link: no session needed, the 32-character token is the credential.
Route::get('r/{token}', PublicReceiptController::class)->middleware('throttle:30,1')->name('receipts.public');

require __DIR__.'/settings.php';
