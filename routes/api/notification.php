<?php

use App\Modules\Notification\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/{notificationId}/read', [NotificationController::class, 'markRead']);
    Route::delete('/{notificationId}', [NotificationController::class, 'destroy']);
});
