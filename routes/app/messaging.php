<?php

declare(strict_types=1);

use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Messaging\SmsSettingsController;
use Illuminate\Support\Facades\Route;

/* SMS: provider settings and the message log (02_AppFlow §2). Loaded inside the branch-selected /app group. */

Route::get('settings/sms', [SmsSettingsController::class, 'edit'])->name('settings.sms.edit');
Route::put('settings/sms', [SmsSettingsController::class, 'update'])->name('settings.sms.update');
Route::post('settings/sms/test', [SmsSettingsController::class, 'test'])->middleware('throttle:10,1')->name('settings.sms.test');

Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
Route::post('messages/send', [MessageController::class, 'send'])->middleware('throttle:10,1')->name('messages.send');
Route::post('messages/{message}/resend', [MessageController::class, 'resend'])->name('messages.resend');
