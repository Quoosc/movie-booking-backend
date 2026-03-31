<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Movie\Controllers\MovieController;

Route::prefix('movies')->group(function () {
    Route::get('/', [MovieController::class, 'index']);
    Route::get('/search/title', [MovieController::class, 'searchByTitle']);
    Route::get('/filter/status', [MovieController::class, 'filterByStatus']);
    Route::get('/filter/genre', [MovieController::class, 'filterByGenre']);
    Route::get('/{movieId}', [MovieController::class, 'show']);
    Route::get('/{movieId}/showtimes', [MovieController::class, 'showtimesByDate']);
});
