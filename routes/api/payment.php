<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Controllers\RefundController;

Route::prefix('payments')->group(function () {
    Route::post('/order', [PaymentController::class, 'initiatePayment'])->middleware('throttle:20,1');
    Route::post('/order/capture', [PaymentController::class, 'capturePayment'])->middleware('throttle:30,1');
    Route::get('/order/status', [PaymentController::class, 'paymentStatus'])->middleware('throttle:60,1');
    Route::get('/momo/ipn', [PaymentController::class, 'handleMomoIpnGet']);
    Route::post('/momo/ipn', [PaymentController::class, 'handleMomoIpnPost']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/search', [PaymentController::class, 'searchPayments']);
    });

    Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
        Route::post('/{paymentId}/refund', [RefundController::class, 'refund']);
    });
});
