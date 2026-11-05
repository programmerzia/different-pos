<?php

declare(strict_types=1);

use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Messaging\MessagePreviewController;
use App\Http\Controllers\Messaging\SmsSettingsController;
use Illuminate\Support\Facades\Route;

/* SMS + WhatsApp: provider settings, the messaging hub and the message log (02_AppFlow §2). Loaded inside the branch-selected /app group. */

Route::get('settings/sms', [SmsSettingsController::class, 'edit'])->name('settings.sms.edit');
Route::put('settings/sms', [SmsSettingsController::class, 'update'])->name('settings.sms.update');
Route::put('settings/sms/sending', [SmsSettingsController::class, 'sending'])->name('settings.sms.sending');
Route::post('settings/sms/test', [SmsSettingsController::class, 'test'])->middleware('throttle:10,1')->name('settings.sms.test');
Route::post('settings/whatsapp/test', [SmsSettingsController::class, 'testWhatsApp'])->middleware('throttle:10,1')->name('settings.whatsapp.test');

Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
Route::get('messages/preview', MessagePreviewController::class)->name('messages.preview');
Route::post('messages/send', [MessageController::class, 'send'])->middleware('throttle:10,1')->name('messages.send');
Route::post('messages/direct', [MessageController::class, 'direct'])->middleware('throttle:30,1')->name('messages.direct');
Route::post('messages/{message}/resend', [MessageController::class, 'resend'])->name('messages.resend');
