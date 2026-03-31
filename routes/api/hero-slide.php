<?php

use App\Modules\HeroSlide\Controllers\HeroSlideController;
use Illuminate\Support\Facades\Route;

Route::prefix('hero-slides')->group(function () {
    Route::get('/', [HeroSlideController::class, 'indexPublic']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/admin', [HeroSlideController::class, 'indexAdmin']);
        Route::post('/', [HeroSlideController::class, 'store']);
        Route::put('/{heroSlideId}', [HeroSlideController::class, 'update']);
        Route::delete('/{heroSlideId}', [HeroSlideController::class, 'destroy']);
    });

    Route::get('/{heroSlideId}', [HeroSlideController::class, 'show']);
});
