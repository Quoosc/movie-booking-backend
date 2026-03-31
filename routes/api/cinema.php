<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Cinema\Controllers\CinemaController;
use App\Modules\Cinema\Controllers\SeatController;

Route::prefix('cinemas')->group(function () {
    Route::get('/', [CinemaController::class, 'index']);
    Route::get('/{cinemaId}/movies', [CinemaController::class, 'moviesByCinema']);
    Route::get('/snacks', [CinemaController::class, 'getSnacks']);
    Route::get('/{cinemaId}/snacks', [CinemaController::class, 'getSnacksByCinema']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/rooms', [CinemaController::class, 'roomsIndex']);
        Route::post('/rooms', [CinemaController::class, 'storeRoom']);
        Route::get('/rooms/{roomId}', [CinemaController::class, 'showRoom']);
        Route::put('/rooms/{roomId}', [CinemaController::class, 'updateRoom']);
        Route::delete('/rooms/{roomId}', [CinemaController::class, 'destroyRoom']);

        Route::post('/', [CinemaController::class, 'store']);
        Route::put('/{cinemaId}', [CinemaController::class, 'update']);
        Route::delete('/{cinemaId}', [CinemaController::class, 'destroy']);

        Route::post('/snacks', [CinemaController::class, 'storeSnack']);
        Route::put('/snacks/{snackId}', [CinemaController::class, 'updateSnack']);
        Route::delete('/snacks/{snackId}', [CinemaController::class, 'deleteSnack']);
        Route::get('/snacks/{snackId}', [CinemaController::class, 'getSnack']);
        Route::get('/snacks/all', [CinemaController::class, 'getAllSnacks']);
    });

    Route::get('/{cinemaId}', [CinemaController::class, 'show']);
});

Route::get('/seats/layout', [SeatController::class, 'layout']);
