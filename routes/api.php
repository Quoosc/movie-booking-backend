<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MembershipTierController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;

// ========== AUTH ==========
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/refresh', [AuthController::class, 'refresh']);
});

// ========== PUBLIC / BROWSING ==========
// (không cần đăng nhập)
Route::prefix('movies')->group(function () {
    // GET /api/movies        -> list + advanced search (title, genre, status)
    Route::get('/', [MovieController::class, 'index']);

    // GET /api/movies/search/title?title=...
    Route::get('/search/title', [MovieController::class, 'searchByTitle']);

    // GET /api/movies/filter/status?status=SHOWING|UPCOMING
    Route::get('/filter/status', [MovieController::class, 'filterByStatus']);

    // GET /api/movies/filter/genre?genre=...
    Route::get('/filter/genre', [MovieController::class, 'filterByGenre']);

    // GET /api/movies/{movieId}
    Route::get('/{movieId}', [MovieController::class, 'show']);

    // GET /api/movies/{movieId}/showtimes?date=YYYY-MM-DD
    Route::get('/{movieId}/showtimes', [MovieController::class, 'showtimesByDate']);
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

    // ========== ADMIN MOVIES (Create/Update/Delete) ==========
    Route::prefix('movies')->group(function () {
        // POST /api/movies
        Route::post('/', [MovieController::class, 'store']);

        // PUT /api/movies/{movieId}
        Route::put('/{movieId}', [MovieController::class, 'update']);

        // DELETE /api/movies/{movieId}
        Route::delete('/{movieId}', [MovieController::class, 'destroy']);
    });
});
