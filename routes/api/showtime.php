<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Showtime\Controllers\ShowtimeController;
use App\Modules\Showtime\Controllers\ShowtimeSeatController;

Route::prefix('showtimes')->group(function () {
    Route::get('/', [ShowtimeController::class, 'index']);
    Route::get('/{showtimeId}', [ShowtimeController::class, 'show']);
    Route::get('/movie/{movieId}', [ShowtimeController::class, 'byMovie']);
    Route::get('/movie/{movieId}/upcoming', [ShowtimeController::class, 'upcomingByMovie']);
    Route::get('/movie/{movieId}/date-range', [ShowtimeController::class, 'byMovieAndDateRange']);
    Route::get('/room/{roomId}', [ShowtimeController::class, 'byRoom']);
});

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('showtime-seats')->group(function () {
        Route::get('/showtime/{showtimeId}', [ShowtimeSeatController::class, 'getByShowtime']);
        Route::get('/showtime/{showtimeId}/available', [ShowtimeSeatController::class, 'getAvailableByShowtime']);
        Route::post('/showtime/{showtimeId}/recalculate-prices', [ShowtimeSeatController::class, 'recalculatePrices']);
        Route::get('/{id}', [ShowtimeSeatController::class, 'getById']);
        Route::put('/{id}', [ShowtimeSeatController::class, 'update']);
        Route::put('/{id}/reset', [ShowtimeSeatController::class, 'reset']);
    });

    Route::prefix('showtimes')->group(function () {
        Route::post('/', [ShowtimeController::class, 'store']);
        Route::put('/{showtimeId}', [ShowtimeController::class, 'update']);
        Route::delete('/{showtimeId}', [ShowtimeController::class, 'destroy']);
    });
});
