<?php

use App\Http\Controllers\Hooks\MpesaCallbackController;
use App\Http\Controllers\Hooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Gateway callbacks (M11B.5, D42): no session, no CSRF. Safaricom refuses callback URLs that
| contain "mpesa" or "safaricom", hence "mobile".
*/

Route::post('paystack', PaystackWebhookController::class)->name('paystack');

Route::middleware('daraja.callback')->prefix('mobile')->name('mobile.')->group(function () {
    Route::post('stk/{secret}', [MpesaCallbackController::class, 'stk'])->name('stk');
    Route::post('c2b/{secret}/confirm', [MpesaCallbackController::class, 'confirm'])->name('c2b.confirm');
    Route::post('c2b/{secret}/validate', [MpesaCallbackController::class, 'validatePayment'])->name('c2b.validate');
});
