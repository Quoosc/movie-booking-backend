<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Pricing\Controllers\PriceBaseController;
use App\Modules\Pricing\Controllers\PriceModifierController;

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('price-base')->group(function () {
        Route::post('/', [PriceBaseController::class, 'store']);
        Route::get('/', [PriceBaseController::class, 'index']);
        Route::get('/active', [PriceBaseController::class, 'getActive']);
        Route::get('/{id}', [PriceBaseController::class, 'show']);
        Route::put('/{id}', [PriceBaseController::class, 'update']);
        Route::delete('/{id}', [PriceBaseController::class, 'destroy']);
    });

    Route::prefix('price-modifiers')->group(function () {
        Route::post('/', [PriceModifierController::class, 'store']);
        Route::get('/', [PriceModifierController::class, 'index']);
        Route::get('/active', [PriceModifierController::class, 'getActive']);
        Route::get('/by-condition', [PriceModifierController::class, 'getByCondition']);
        Route::get('/{id}', [PriceModifierController::class, 'show']);
        Route::put('/{id}', [PriceModifierController::class, 'update']);
        Route::delete('/{id}', [PriceModifierController::class, 'destroy']);
    });
});
