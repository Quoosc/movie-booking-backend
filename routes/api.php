<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MembershipTierController;
use App\Http\Controllers\BookingController;

// ========== AUTH ==========
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/refresh', [AuthController::class, 'refresh']);
});

// Các route cần đăng nhập (dùng middleware JWT giống anh đang dùng cho /users/...)
Route::middleware('auth.jwt')->group(function () {

    // ========== USER PROFILE ==========
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UsersController::class, 'getProfile']);
        Route::put('/profile', [UsersController::class, 'updateProfile']);
        Route::patch('/password', [UsersController::class, 'updatePassword']);
        Route::get('/loyalty', [UsersController::class, 'getLoyalty']);
    });

    // ========== MEMBERSHIP TIERS ==========
    Route::prefix('membership-tiers')->group(function () {
        // GET /api/membership-tiers/active
        Route::get('/active', [MembershipTierController::class, 'getActiveTiers']);
    });

    // ========== BOOKINGS (history + detail cho user) ==========
    Route::prefix('bookings')->group(function () {
        // GET /api/bookings/my-bookings
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);

        // GET /api/bookings/{bookingId}
        Route::get('/{bookingId}', [BookingController::class, 'show']);
    });
});
