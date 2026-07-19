<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('login-with-password', [AuthController::class, 'loginWithPassword'])->middleware('throttle:auth-login');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth-verify-otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('avatar', [ProfileController::class, 'destroyAvatar']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);
        Route::post('switch-active-role', [ProfileController::class, 'switchActiveRole']);
        Route::get('avatar/{user}', [ProfileController::class, 'serveAvatar'])->name('auth.avatar.serve');
    });
});
