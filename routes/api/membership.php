<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Membership\Controllers\MembershipTierController;

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('membership-tiers')->group(function () {
        Route::get('/', [MembershipTierController::class, 'index']);
        Route::get('/active', [MembershipTierController::class, 'getActive']);
        Route::get('/name/{name}', [MembershipTierController::class, 'getByName']);
        Route::get('/{id}', [MembershipTierController::class, 'show']);

        Route::post('/', [MembershipTierController::class, 'store']);
        Route::put('/{id}', [MembershipTierController::class, 'update']);
        Route::patch('/{id}/deactivate', [MembershipTierController::class, 'deactivate']);
        Route::delete('/{id}', [MembershipTierController::class, 'destroy']);
    });
});
