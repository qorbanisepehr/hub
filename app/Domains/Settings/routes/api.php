<?php

use App\Domains\Settings\Controllers\BrandingController;
use Illuminate\Support\Facades\Route;

// Public immutable image endpoints. Version the URL with the file mtime so a
// re-upload produces a fresh URL and browsers never serve a stale file.
Route::get('settings/branding/logo', [BrandingController::class, 'logo'])
    ->name('settings.branding.logo');
Route::get('settings/branding/logotype', [BrandingController::class, 'logotype'])
    ->name('settings.branding.logotype');
Route::get('settings/branding/favicon', [BrandingController::class, 'favicon'])
    ->name('settings.branding.favicon');
Route::get('settings/branding/og_image', [BrandingController::class, 'ogImage'])
    ->name('settings.branding.og_image');

// Admin management
Route::middleware(['auth:sanctum', 'permission:branding.manage'])->group(function () {
    Route::put('settings/branding', [BrandingController::class, 'update']);
    Route::post('settings/branding/logo', [BrandingController::class, 'uploadLogo']);
    Route::delete('settings/branding/logo', [BrandingController::class, 'deleteLogo']);
    Route::post('settings/branding/logotype', [BrandingController::class, 'uploadLogotype']);
    Route::delete('settings/branding/logotype', [BrandingController::class, 'deleteLogotype']);
    Route::post('settings/branding/favicon', [BrandingController::class, 'uploadFavicon']);
    Route::delete('settings/branding/favicon', [BrandingController::class, 'deleteFavicon']);
    Route::post('settings/branding/og_image', [BrandingController::class, 'uploadOgImage']);
    Route::delete('settings/branding/og_image', [BrandingController::class, 'deleteOgImage']);
});
