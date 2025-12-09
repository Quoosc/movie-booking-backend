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
use App\Http\Controllers\ShowtimeController;

use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\ShowtimeTicketTypeController;
use App\Http\Controllers\PriceBaseController;
use App\Http\Controllers\PriceModifierController;




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


// ========== PUBLIC / BROWSING: SHOWTIMES ==========
Route::prefix('showtimes')->group(function () {
    Route::get('/', [ShowtimeController::class, 'index']);
    Route::get('/{showtimeId}', [ShowtimeController::class, 'show']);
    Route::get('/movie/{movieId}', [ShowtimeController::class, 'byMovie']);
    Route::get('/movie/{movieId}/upcoming', [ShowtimeController::class, 'upcomingByMovie']);
    Route::get('/movie/{movieId}/date-range', [ShowtimeController::class, 'byMovieAndDateRange']);
    Route::get('/room/{roomId}', [ShowtimeController::class, 'byRoom']);
});

// ========== PUBLIC: TICKET TYPES (guest/user) ==========
Route::get('/ticket-types', [TicketTypeController::class, 'index']);



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

    // ========== ADMIN: SHOWTIMES ==========
    Route::prefix('showtimes')->group(function () {
        Route::post('/', [ShowtimeController::class, 'store']);
        Route::put('/{showtimeId}', [ShowtimeController::class, 'update']);
        Route::delete('/{showtimeId}', [ShowtimeController::class, 'destroy']);
    });


    // ===== ADMIN: TICKET TYPES =====
    Route::prefix('ticket-types')->group(function () {
        Route::get('/admin', [TicketTypeController::class, 'adminIndex']);
        Route::post('/', [TicketTypeController::class, 'store']);
        Route::put('/{id}', [TicketTypeController::class, 'update']);
        Route::delete('/{id}', [TicketTypeController::class, 'destroy']);
    });

    // ===== ADMIN: SHOWTIME TICKET TYPES =====
    Route::prefix('showtimes/{showtimeId}/ticket-types')->group(function () {
        Route::get('/', [ShowtimeTicketTypeController::class, 'index']);
        Route::post('/', [ShowtimeTicketTypeController::class, 'assignMultiple']);
        Route::post('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'assignSingle']);
        Route::put('/', [ShowtimeTicketTypeController::class, 'replace']);
        Route::delete('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'remove']);
    });

    // ===== PRICING: PRICE BASE =====
    Route::prefix('price-base')->group(function () {
        Route::post('/', [PriceBaseController::class, 'store']);      // POST /api/price-base
        Route::get('/',  [PriceBaseController::class, 'index']);      // GET /api/price-base
        Route::get('/active', [PriceBaseController::class, 'getActive']); // GET /api/price-base/active
        Route::get('/{id}', [PriceBaseController::class, 'show']);    // GET /api/price-base/{id}
        Route::put('/{id}', [PriceBaseController::class, 'update']);  // PUT /api/price-base/{id}
        Route::delete('/{id}', [PriceBaseController::class, 'destroy']); // DELETE /api/price-base/{id}
    });

    // ===== PRICING: PRICE MODIFIERS =====
    Route::prefix('price-modifiers')->group(function () {
        Route::post('/', [PriceModifierController::class, 'store']);      // POST /api/price-modifiers
        Route::get('/',  [PriceModifierController::class, 'index']);      // GET /api/price-modifiers
        Route::get('/active', [PriceModifierController::class, 'getActive']); // GET /api/price-modifiers/active
        Route::get('/by-condition', [PriceModifierController::class, 'getByCondition']); // GET /api/price-modifiers/by-condition?conditionType=...
        Route::get('/{id}', [PriceModifierController::class, 'show']);    // GET /api/price-modifiers/{id}
        Route::put('/{id}', [PriceModifierController::class, 'update']);  // PUT /api/price-modifiers/{id}
        Route::delete('/{id}', [PriceModifierController::class, 'destroy']); // DELETE /api/price-modifiers/{id}
    });
});
