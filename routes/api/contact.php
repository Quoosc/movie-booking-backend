<?php

use App\Modules\Contact\Controllers\ContactSubmissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('contact')->group(function () {
    Route::post('/submissions', [ContactSubmissionController::class, 'store'])
        ->middleware('throttle:8,1');
});
