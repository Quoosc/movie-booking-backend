<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\UsersController;

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UsersController::class, 'getProfile']);
        Route::put('/profile', [UsersController::class, 'updateProfile']);
        Route::patch('/password', [UsersController::class, 'updatePassword']);
        Route::get('/loyalty', [UsersController::class, 'getLoyalty']);

        Route::get('/', [UsersController::class, 'listAllUsers']);
        Route::get('/{userId}', [UsersController::class, 'getUserById']);
        Route::patch('/{userId}/role', [UsersController::class, 'updateUserRole']);
        Route::delete('/{userId}', [UsersController::class, 'deleteUser']);
    });
});
