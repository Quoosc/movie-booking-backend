<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Ticketing\Controllers\TicketTypeController;
use App\Modules\Ticketing\Controllers\ShowtimeTicketTypeController;

Route::get('/ticket-types', [TicketTypeController::class, 'index']);

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('ticket-types')->group(function () {
        Route::get('/admin', [TicketTypeController::class, 'adminIndex']);
        Route::post('/', [TicketTypeController::class, 'store']);
        Route::put('/{id}', [TicketTypeController::class, 'update']);
        Route::delete('/{id}', [TicketTypeController::class, 'destroy']);
    });

    Route::prefix('showtimes/{showtimeId}/ticket-types')->group(function () {
        Route::get('/', [ShowtimeTicketTypeController::class, 'index']);
        Route::post('/', [ShowtimeTicketTypeController::class, 'assignMultiple']);
        Route::post('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'assignSingle']);
        Route::put('/', [ShowtimeTicketTypeController::class, 'replace']);
        Route::delete('/{ticketTypeId}', [ShowtimeTicketTypeController::class, 'remove']);
    });
});
