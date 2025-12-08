<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MembershipTierController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\CinemaController;
use App\Http\Controllers\SeatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ========== AUTH ==========
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/refresh', [AuthController::class, 'refresh']);
});


// ========== PUBLIC / BROWSING: MOVIES ==========
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


// ========== PUBLIC + ADMIN: CINEMAS ==========
Route::prefix('cinemas')->group(function () {
    // ===== PUBLIC =====
    // GET /api/cinemas
    Route::get('/', [CinemaController::class, 'index']);

    // GET /api/cinemas/{cinemaId}/movies?status=SHOWING
    Route::get('/{cinemaId}/movies', [CinemaController::class, 'moviesByCinema']);

    // ===== ADMIN (cần token, dùng auth.jwt) =====
    Route::middleware('auth.jwt')->group(function () {

        // --- ROOMS ---
        // GET /api/cinemas/rooms?cinemaId=...
        Route::get('/rooms', [CinemaController::class, 'roomsIndex']);

        // POST /api/cinemas/rooms
        Route::post('/rooms', [CinemaController::class, 'storeRoom']);

        Route::get('/rooms/{roomId}', [CinemaController::class, 'showRoom']);

        Route::get('/{cinemaId}/movies', [CinemaController::class, 'moviesByCinema']);

        // PUT /api/cinemas/rooms/{roomId}
        Route::put('/rooms/{roomId}', [CinemaController::class, 'updateRoom']);

        // DELETE /api/cinemas/rooms/{roomId}
        Route::delete('/rooms/{roomId}', [CinemaController::class, 'destroyRoom']);

        // --- CINEMAS CRUD ---
        // POST /api/cinemas
        Route::post('/', [CinemaController::class, 'store']);

        // PUT /api/cinemas/{cinemaId}
        Route::put('/{cinemaId}', [CinemaController::class, 'update']);

        // DELETE /api/cinemas/{cinemaId}
        Route::delete('/{cinemaId}', [CinemaController::class, 'destroy']);
    });

    // Đặt SAU cùng để không nuốt /rooms
    // GET /api/cinemas/{cinemaId}
    Route::get('/{cinemaId}', [CinemaController::class, 'show']);
});


// ========== CÁC ROUTE CẦN ĐĂNG NHẬP (USER, BOOKING, ADMIN MOVIE, SEAT) ==========
Route::middleware('auth.jwt')->group(function () {

    // ----- USER PROFILE -----
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UsersController::class, 'getProfile']);
        Route::put('/profile', [UsersController::class, 'updateProfile']);
        Route::patch('/password', [UsersController::class, 'updatePassword']);
        Route::get('/loyalty', [UsersController::class, 'getLoyalty']);
    });

    // ----- MEMBERSHIP TIERS -----
    Route::prefix('membership-tiers')->group(function () {
        // GET /api/membership-tiers/active
        Route::get('/active', [MembershipTierController::class, 'getActiveTiers']);
    });

    // ----- BOOKINGS (history + detail) -----
    Route::prefix('bookings')->group(function () {
        // GET /api/bookings/my-bookings
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);

        // GET /api/bookings/{bookingId}
        Route::get('/{bookingId}', [BookingController::class, 'show']);
    });

    // ----- ADMIN MOVIES (Create / Update / Delete) -----
    Route::prefix('movies')->group(function () {
        // POST /api/movies
        Route::post('/', [MovieController::class, 'store']);

        // PUT /api/movies/{movieId}
        Route::put('/{movieId}', [MovieController::class, 'update']);

        // DELETE /api/movies/{movieId}
        Route::delete('/{movieId}', [MovieController::class, 'destroy']);
    });

    // ----- ADMIN SEATS -----
    Route::prefix('seats')->group(function () {
        // POST /api/seats/generate
        Route::post('/generate', [SeatController::class, 'generate']);

        // GET /api/seats/row-labels?rows=10
        Route::get('/row-labels', [SeatController::class, 'rowLabels']);
    });
});
