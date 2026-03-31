<?php

use Illuminate\Support\Facades\Route;

Route::post('/test-guest', function () {
    return ['status' => 'ok', 'message' => 'Guest route working!'];
});
