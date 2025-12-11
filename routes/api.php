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
use App\Http\Controllers\ShowtimeSeatController;

use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\ShowtimeTicketTypeController;
use App\Http\Controllers\PriceBaseController;
use App\Http\Controllers\PriceModifierController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\Payments\PayPalController;
use App\Http\Controllers\Payments\MomoController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SeatLockController;
use App\Http\Controllers\RefundController;

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


// ======================================================================
// PUBLIC / BROWSING: MOVIES
// ======================================================================
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


// ======================================================================
// PUBLIC + ADMIN: CINEMAS
// ======================================================================
Route::prefix('cinemas')->group(function () {
    // PUBLIC
    Route::get('/', [CinemaController::class, 'index']);
    Route::get('/{cinemaId}/movies', [CinemaController::class, 'moviesByCinema']);
    Route::get('/{cinemaId}/snacks', [CinemaController::class, 'getSnacksByCinema']);

    // ===== ADMIN (cần token, dùng auth.jwt) =====
    Route::middleware('auth.jwt')->group(function () {

        // ROOMS
        Route::get('/rooms', [CinemaController::class, 'roomsIndex']);
        Route::post('/rooms', [CinemaController::class, 'storeRoom']);
        Route::get('/rooms/{roomId}', [CinemaController::class, 'showRoom']);
        Route::put('/rooms/{roomId}', [CinemaController::class, 'updateRoom']);
        Route::delete('/rooms/{roomId}', [CinemaController::class, 'destroyRoom']);

        // CINEMAS CRUD
        Route::post('/', [CinemaController::class, 'store']);
        Route::put('/{cinemaId}', [CinemaController::class, 'update']);
        Route::delete('/{cinemaId}', [CinemaController::class, 'destroy']);

        // SNACKS ADMIN
        Route::post('/snacks', [CinemaController::class, 'storeSnack']);
        Route::put('/snacks/{snackId}', [CinemaController::class, 'updateSnack']);
        Route::delete('/snacks/{snackId}', [CinemaController::class, 'deleteSnack']);
        Route::get('/snacks/{snackId}', [CinemaController::class, 'getSnack']);
        Route::get('/snacks', [CinemaController::class, 'getAllSnacks']);
    });

    // Đặt SAU cùng để không nuốt /rooms
    // GET /api/cinemas/{cinemaId}
    Route::get('/{cinemaId}', [CinemaController::class, 'show']);
});


// ======================================================================
// PUBLIC / BROWSING: SHOWTIMES
// ======================================================================
Route::prefix('showtimes')->group(function () {
    Route::get('/', [ShowtimeController::class, 'index']);
    Route::get('/{showtimeId}', [ShowtimeController::class, 'show']);
    Route::get('/movie/{movieId}', [ShowtimeController::class, 'byMovie']);
    Route::get('/movie/{movieId}/upcoming', [ShowtimeController::class, 'upcomingByMovie']);
    Route::get('/movie/{movieId}/date-range', [ShowtimeController::class, 'byMovieAndDateRange']);
    Route::get('/room/{roomId}', [ShowtimeController::class, 'byRoom']);
});


// ======================================================================
// PUBLIC: TICKET TYPES (guest/user)
// ======================================================================
Route::get('/ticket-types', [TicketTypeController::class, 'index']);



// PUBLIC: seat layout cho 1 showtime
Route::get('/seats/layout', [SeatController::class, 'layout']);

// ======================================================================
// PUBLIC: PROMOTIONS
// ======================================================================
Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);                 // GET /api/promotions?filter=
    Route::get('/active', [PromotionController::class, 'getActive']);      // GET /api/promotions/active
    Route::get('/valid', [PromotionController::class, 'getValid']);        // GET /api/promotions/valid
    Route::get('/code/{code}', [PromotionController::class, 'showByCode']); // GET /api/promotions/code/{code}
    Route::get('/{promotionId}', [PromotionController::class, 'show']);    // GET /api/promotions/{id}
});


// ======================================================================
// CÁC ROUTE CẦN ĐĂNG NHẬP (USER, BOOKING, ADMIN ...)
// ======================================================================
Route::middleware('auth.jwt')->group(function () {

    // ------------------------------------------------------------------
    // USER PROFILE
    // ------------------------------------------------------------------
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UsersController::class, 'getProfile']);
        Route::put('/profile', [UsersController::class, 'updateProfile']);
        Route::patch('/password', [UsersController::class, 'updatePassword']);
        Route::get('/loyalty', [UsersController::class, 'getLoyalty']);
    });

    // ------------------------------------------------------------------
    // MEMBERSHIP TIERS
    // ------------------------------------------------------------------
    Route::prefix('membership-tiers')->group(function () {
        // LIST + GET
        Route::get('/', [MembershipTierController::class, 'index']);              // GET /api/membership-tiers
        Route::get('/active', [MembershipTierController::class, 'getActive']);   // GET /api/membership-tiers/active
        Route::get('/name/{name}', [MembershipTierController::class, 'getByName']); // GET /name/{name}
        Route::get('/{id}', [MembershipTierController::class, 'show']);          // GET /{id}

        // ADMIN CRUD
        Route::post('/', [MembershipTierController::class, 'store']);            // POST
        Route::put('/{id}', [MembershipTierController::class, 'update']);        // PUT
        Route::patch('/{id}/deactivate', [MembershipTierController::class, 'deactivate']); // PATCH
        Route::delete('/{id}', [MembershipTierController::class, 'destroy']);    // DELETE
    });

    // ------------------------------------------------------------------
    // BOOKINGS (lock seats + create booking + history)
    // ------------------------------------------------------------------
    // ========== BOOKING FLOW ==========
    // ====== SEAT LOCKS ======
    Route::prefix('seat-locks')->group(function () {
        Route::post('/', [SeatLockController::class, 'lockSeats']);
        Route::get('/availability/showtime/{showtimeId}', [SeatLockController::class, 'checkAvailability']);
        Route::delete('/showtime/{showtimeId}', [SeatLockController::class, 'releaseSeats']);
    });

    // ====== BOOKINGS ======
    Route::prefix('bookings')->group(function () {
        Route::post('/price-preview', [BookingController::class, 'pricePreview']);
        Route::post('/confirm',       [BookingController::class, 'confirmBooking']);

        Route::get('/my-bookings',              [BookingController::class, 'getUserBookings']);
        Route::get('/{bookingId}',              [BookingController::class, 'getBookingById']);
        Route::patch('/{bookingId}/qr',         [BookingController::class, 'updateQrCode']);
    });

    // ====== CHECKOUT ======
    Route::post('/checkout', [CheckoutController::class, 'confirmAndInitiate']);


    // ========== PAYMENTS (MOMO + PAYPAL + REFUND) ==========
    Route::prefix('payments')->group(function () {
        // PAYPAL
        Route::prefix('paypal')->group(function () {
            // POST /api/payments/paypal/create-order
            Route::post('create-order',  [PayPalController::class, 'createOrder']);
            // POST /api/payments/paypal/capture-order
            Route::post('capture-order', [PayPalController::class, 'captureOrder']);
        });

        // MOMO
        Route::prefix('momo')->group(function () {
            // POST /api/payments/momo/create-order
            Route::post('create-order', [MomoController::class, 'createOrder']);
            // POST /api/payments/momo/ipn
            Route::post('ipn',          [MomoController::class, 'ipn']);
            // GET /api/payments/momo/verify?orderId=...
            Route::get('verify',        [MomoController::class, 'verifyPayment']);
        });

        // REFUND (admin)
        Route::middleware('can:admin')->group(function () {
            // POST /api/payments/{paymentId}/refund
            Route::post('{paymentId}/refund', [RefundController::class, 'refund']);
        });
    });

    // ------------------------------------------------------------------
    // ADMIN MOVIES (Create / Update / Delete)
    // ------------------------------------------------------------------
    Route::prefix('movies')->group(function () {
        Route::post('/', [MovieController::class, 'store']);
        Route::put('/{movieId}', [MovieController::class, 'update']);
        Route::delete('/{movieId}', [MovieController::class, 'destroy']);
    });

    // ===== ADMIN SEATS =====
    Route::prefix('seats')->group(function () {
        // Tools đặt trước đường /{seatId} để tránh nuốt route
        Route::get('/row-labels', [SeatController::class, 'rowLabels']);     // GET /seats/row-labels?rows=10
        Route::post('/generate',   [SeatController::class, 'generate']);     // POST /seats/generate
        Route::get('/room/{roomId}', [SeatController::class, 'getByRoom']);  // GET /seats/room/{roomId}
        Route::get('/layout',        [SeatController::class, 'layout']);     // GET /seats/layout?showtimeId=...

        // CRUD cơ bản cho seat (admin)
        Route::get('/',           [SeatController::class, 'index']);   // GET /seats
        Route::post('/',          [SeatController::class, 'store']);   // POST /seats
        Route::get('/{seatId}',   [SeatController::class, 'show']);    // GET /seats/{seatId}
        Route::put('/{seatId}',   [SeatController::class, 'update']);  // PUT /seats/{seatId}
        Route::delete('/{seatId}', [SeatController::class, 'destroy']); // DELETE /seats/{seatId}
    });

    // ===== SHOWTIME SEATS (admin tools) =====
    Route::prefix('showtime-seats')->group(function () {
        Route::get('/showtime/{showtimeId}',             [ShowtimeSeatController::class, 'getByShowtime']);
        Route::get('/showtime/{showtimeId}/available',   [ShowtimeSeatController::class, 'getAvailableByShowtime']);
        Route::post('/showtime/{showtimeId}/recalculate-prices', [ShowtimeSeatController::class, 'recalculatePrices']);

        Route::get('/{id}',        [ShowtimeSeatController::class, 'getById']);
        Route::put('/{id}',        [ShowtimeSeatController::class, 'update']);      // nếu muốn chỉnh tay status/price
        Route::put('/{id}/reset',  [ShowtimeSeatController::class, 'reset']);       // reset về AVAILABLE
    });


    // ------------------------------------------------------------------
    // ADMIN: SHOWTIMES CRUD
    // ------------------------------------------------------------------
    Route::prefix('showtimes')->group(function () {
        Route::post('/', [ShowtimeController::class, 'store']);
        Route::put('/{showtimeId}', [ShowtimeController::class, 'update']);
        Route::delete('/{showtimeId}', [ShowtimeController::class, 'destroy']);
    });


    // ------------------------------------------------------------------
    // ADMIN: TICKET TYPES
    // ------------------------------------------------------------------
    Route::prefix('ticket-types')->group(function () {
        Route::get('/admin', [TicketTypeController::class, 'adminIndex']);
        Route::post('/', [TicketTypeController::class, 'store']);
        Route::put('/{id}', [TicketTypeController::class, 'update']);
        Route::delete('/{id}', [TicketTypeController::class, 'destroy']);
    });

    // ------------------------------------------------------------------
    // ADMIN: SHOWTIME TICKET TYPES
    // ------------------------------------------------------------------
    Route::prefix('showtimes/{showtimeId}/ticket-types')->group(function () {
        Route::get('/', [ShowtimeTicketTypeController::class, 'index']);
        Route::post('/', [ShowtimeTicketTypeController::class, 'assignMultiple']);
        Route::post('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'assignSingle']);
        Route::put('/', [ShowtimeTicketTypeController::class, 'replace']);
        Route::delete('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'remove']);
    });

    // ------------------------------------------------------------------
    // PRICING: PRICE BASE
    // ------------------------------------------------------------------
    Route::prefix('price-base')->group(function () {
        Route::post('/', [PriceBaseController::class, 'store']);          // POST /api/price-base
        Route::get('/',  [PriceBaseController::class, 'index']);          // GET /api/price-base
        Route::get('/active', [PriceBaseController::class, 'getActive']); // GET /api/price-base/active
        Route::get('/{id}', [PriceBaseController::class, 'show']);        // GET /api/price-base/{id}
        Route::put('/{id}', [PriceBaseController::class, 'update']);      // PUT /api/price-base/{id}
        Route::delete('/{id}', [PriceBaseController::class, 'destroy']);  // DELETE /api/price-base/{id}
    });

    // ------------------------------------------------------------------
    // PRICING: PRICE MODIFIERS
    // ------------------------------------------------------------------
    Route::prefix('price-modifiers')->group(function () {
        Route::post('/', [PriceModifierController::class, 'store']);          // POST /api/price-modifiers
        Route::get('/',  [PriceModifierController::class, 'index']);          // GET /api/price-modifiers
        Route::get('/active', [PriceModifierController::class, 'getActive']); // GET /api/price-modifiers/active
        Route::get('/by-condition', [PriceModifierController::class, 'getByCondition']); // GET /api/price-modifiers/by-condition?conditionType=...
        Route::get('/{id}', [PriceModifierController::class, 'show']);        // GET /api/price-modifiers/{id}
        Route::put('/{id}', [PriceModifierController::class, 'update']);      // PUT /api/price-modifiers/{id}
        Route::delete('/{id}', [PriceModifierController::class, 'destroy']);  // DELETE /api/price-modifiers/{id}
    });

    // ------------------------------------------------------------------
    // ADMIN: PROMOTIONS
    // ------------------------------------------------------------------
    Route::prefix('promotions')->group(function () {
        Route::post('/', [PromotionController::class, 'store']);                      // POST /api/promotions
        Route::put('/{promotionId}', [PromotionController::class, 'update']);         // PUT /api/promotions/{id}
        Route::patch('/{promotionId}/deactivate', [PromotionController::class, 'deactivate']); // PATCH /api/promotions/{id}/deactivate
        Route::delete('/{promotionId}', [PromotionController::class, 'destroy']);     // DELETE /api/promotions/{id}
    });
});
