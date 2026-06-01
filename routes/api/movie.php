<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Movie\Controllers\MovieController;
use App\Modules\Movie\Controllers\MovieReviewController;

Route::prefix('movies')->group(function () {
    Route::get('/', [MovieController::class, 'index']);
    Route::get('/search/title', [MovieController::class, 'searchByTitle']);
    Route::get('/filter/status', [MovieController::class, 'filterByStatus']);
    Route::get('/filter/genre', [MovieController::class, 'filterByGenre']);
    Route::get('/{movieId}', [MovieController::class, 'show']);
    Route::get('/{movieId}/showtimes', [MovieController::class, 'showtimesByDate']);
    Route::get('/{movieId}/reviews', [MovieReviewController::class, 'index']);
    Route::post('/{movieId}/reviews', [MovieReviewController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::post('/{movieId}/reviews/{reviewId}/react', [MovieReviewController::class, 'react'])
        ->middleware('throttle:30,1');
    Route::post('/{movieId}/reviews/{reviewId}/reply', [MovieReviewController::class, 'reply'])
        ->middleware('throttle:10,1');
});
