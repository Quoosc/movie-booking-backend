<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Booking\Controllers\BookingController;
use App\Modules\Booking\Controllers\CheckoutController;
use App\Modules\Booking\Controllers\SeatLockController;

Route::prefix('seat-locks')->group(function () {
    Route::post('/', [SeatLockController::class, 'lockSeats']);
    Route::get('/availability/{showtimeId}', [SeatLockController::class, 'checkAvailability']);
    Route::delete('/showtime/{showtimeId}', [SeatLockController::class, 'releaseSeats']);
});

Route::prefix('bookings')->group(function () {
    Route::post('/price-preview', [BookingController::class, 'pricePreview']);
    Route::post('/confirm', [BookingController::class, 'confirmBooking']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/my-bookings', [BookingController::class, 'getUserBookings']);
        Route::patch('/{bookingId}/qr', [BookingController::class, 'updateQrCode']);
    });

    Route::middleware('auth.optional')->get('/{bookingId}', [BookingController::class, 'showBookingPublic']);
});

Route::post('/checkout', [CheckoutController::class, 'confirmAndInitiate']);
