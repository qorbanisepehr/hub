<?php

use App\Domains\Notification\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// User-scoped notification endpoints (self-service, intrinsic to the
// authenticated user — no permission group, see architecture §122).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');

    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::post('notifications/delete-read', [NotificationController::class, 'deleteRead'])
        ->name('notifications.delete-read');

    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});
