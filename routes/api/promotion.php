<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Promotion\Controllers\PromotionController;

Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::get('/active', [PromotionController::class, 'getActive']);
    Route::get('/valid', [PromotionController::class, 'getValid']);
    Route::get('/code/{code}', [PromotionController::class, 'showByCode']);
    Route::get('/{promotionId}', [PromotionController::class, 'show']);
});
