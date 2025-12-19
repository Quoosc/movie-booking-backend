<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OAuthController;

Route::get('/', function () {
    return view('welcome');
});

// compatible OAuth2 endpoints (public)
Route::get('/oauth2/authorization/google', [OAuthController::class, 'redirectToGoogle']);
Route::get('/login/oauth2/code/google', [OAuthController::class, 'handleGoogleCallback']);
