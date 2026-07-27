<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Movie\Controllers\MovieController;
use App\Modules\Movie\Controllers\MovieReviewController;

Route::prefix('movies')->group(function () {
    Route::get('/', [MovieController::class, 'index']);
    Route::get('/search/title', [MovieController::class, 'searchByTitle']);
    Route::get('/filter/status', [MovieController::class, 'filterByStatus']);
    Route::get('/filter/genre', [MovieController::class, 'filterByGenre']);
    Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
        Route::get('/reviews/moderation', [MovieReviewController::class, 'moderationIndex']);
        Route::patch('/reviews/{reviewId}/moderate', [MovieReviewController::class, 'moderate']);
    });
    Route::get('/{movieId}', [MovieController::class, 'show']);
    Route::get('/{movieId}/related', [MovieController::class, 'related']);
    Route::get('/{movieId}/showtimes', [MovieController::class, 'showtimesByDate']);
    Route::get('/{movieId}/reviews', [MovieReviewController::class, 'index'])->middleware('auth.optional');
    Route::post('/{movieId}/reviews', [MovieReviewController::class, 'store'])
        ->middleware(['auth.optional', 'throttle:10,1']);
    Route::post('/{movieId}/reviews/{reviewId}/react', [MovieReviewController::class, 'react'])
        ->middleware(['auth.optional', 'throttle:30,1']);
    Route::post('/{movieId}/reviews/{reviewId}/reply', [MovieReviewController::class, 'reply'])
        ->middleware(['auth.optional', 'throttle:10,1']);
    Route::post('/{movieId}/reviews/{reviewId}/report', [MovieReviewController::class, 'report'])
        ->middleware(['auth.optional', 'throttle:5,1']);
    Route::middleware('auth.jwt')->group(function () {
        Route::put('/{movieId}/reviews/{reviewId}', [MovieReviewController::class, 'update']);
        Route::delete('/{movieId}/reviews/{reviewId}', [MovieReviewController::class, 'destroy']);
    });
});
