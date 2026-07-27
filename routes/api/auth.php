<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->middleware('auth.jwt');
    Route::match(['get', 'post'], '/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:30,1');
});
