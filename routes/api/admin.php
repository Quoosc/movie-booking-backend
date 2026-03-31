<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Movie\Controllers\MovieController;
use App\Modules\Cinema\Controllers\SeatController;
use App\Modules\Promotion\Controllers\PromotionController;

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('movies')->group(function () {
        Route::post('/', [MovieController::class, 'store']);
        Route::put('/{movieId}', [MovieController::class, 'update']);
        Route::delete('/{movieId}', [MovieController::class, 'destroy']);
    });

    Route::prefix('seats')->group(function () {
        Route::get('/row-labels', [SeatController::class, 'rowLabels']);
        Route::post('/generate', [SeatController::class, 'generate']);
        Route::get('/room/{roomId}', [SeatController::class, 'getByRoom']);

        Route::get('/', [SeatController::class, 'index']);
        Route::post('/', [SeatController::class, 'store']);
        Route::get('/{seatId}', [SeatController::class, 'show']);
        Route::put('/{seatId}', [SeatController::class, 'update']);
        Route::delete('/{seatId}', [SeatController::class, 'destroy']);
    });

    Route::prefix('promotions')->group(function () {
        Route::post('/', [PromotionController::class, 'store']);
        Route::put('/{promotionId}', [PromotionController::class, 'update']);
        Route::patch('/{promotionId}/deactivate', [PromotionController::class, 'deactivate']);
        Route::delete('/{promotionId}', [PromotionController::class, 'destroy']);
    });
});
