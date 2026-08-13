<?php

use App\Domains\Cv\Controllers\CvBankController;
use App\Domains\Cv\Controllers\CvController;
use App\Domains\Cv\Controllers\CvDocumentController;
use App\Http\Controllers\GrantAccessController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('cv/init', [CvController::class, 'init'])
    ->middleware('throttle:10,1');
Route::post('cv/verify-init-otp', [CvController::class, 'verifyInitOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// OTP endpoints
Route::post('cv/pending/{uuid}/send-otp', [CvController::class, 'resendInitOtp'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('cv/{uuid}/send-mobile-otp', [CvController::class, 'sendMobileOtp'])
    ->whereUuid('uuid')
    ->middleware('throttle:questionnaire-otp-send');
Route::post('cv/{uuid}/send-email-otp', [CvController::class, 'sendEmailOtp'])
    ->whereUuid('uuid')
    ->middleware('throttle:questionnaire-otp-send');
Route::post('cv/{uuid}/verify-mobile-otp', [CvController::class, 'verifyMobileOtp'])
    ->whereUuid('uuid')
    ->middleware('throttle:questionnaire-otp-verify');
Route::post('cv/{uuid}/verify-email-otp', [CvController::class, 'verifyEmailOtp'])
    ->whereUuid('uuid')
    ->middleware('throttle:questionnaire-otp-verify');

// Grant issuance (public, OTP-gated) for cv entities
Route::post('{entity}/{uuid}/request-access', [GrantAccessController::class, 'requestAccess'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('{entity}/{uuid}/verify-access-otp', [GrantAccessController::class, 'verifyAccessOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// Existence check (public) so clients can show a 404 before offering the
// protected access form.
Route::get('{entity}/{uuid}/exists', [GrantAccessController::class, 'exists']);

// Per-category document requirements for the CV flow (public — no sensitive
// data). Registered before the {uuid} routes so it never binds to cv/{uuid}.
Route::get('cv/document-requirements', [CvDocumentController::class, 'requirements']);

// Read-only access (grant.access:cv,view)
Route::middleware('grant.access:cv,view')->group(function () {
    Route::get('cv/{uuid}', [CvController::class, 'show'])->whereUuid('uuid');
    Route::get('cv/{uuid}/documents', [CvDocumentController::class, 'index'])->whereUuid('uuid');
});

// Editing access (grant.access:cv,edit)
Route::middleware('grant.access:cv,edit')->group(function () {
    Route::put('cv/{uuid}/sections/{section}', [CvController::class, 'saveSection'])->whereUuid('uuid');
    Route::post('cv/{uuid}/submit', [CvController::class, 'submit'])->whereUuid('uuid');
    Route::post('cv/{uuid}/documents', [CvDocumentController::class, 'store'])
        ->whereUuid('uuid')
        ->middleware('throttle:30,1');
    Route::delete('cv/{uuid}/documents/{usageId}', [CvDocumentController::class, 'destroy'])->whereUuid('uuid');
});

// Signed document serving stays public because <img> can't send headers.
Route::get('cv/documents/{uuid}/serve', [CvDocumentController::class, 'serve'])
    ->name('cv.documents.serve')
    ->middleware('signed:thumbnail');

// Protected routes (HR management)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('cv/bank', [CvBankController::class, 'index'])->middleware('permission:cv.view');
    Route::get('cv/bank/{cv}', [CvBankController::class, 'show'])->middleware('permission:cv.view');
    Route::post('cv/{uuid}/approve', [CvController::class, 'approve'])
        ->whereUuid('uuid')
        ->middleware('permission:cv.approve');
    Route::post('cv/{uuid}/reject', [CvController::class, 'reject'])
        ->whereUuid('uuid')
        ->middleware('permission:cv.reject');
    Route::post('cv/bank/{cv}/questionnaire', [CvBankController::class, 'createQuestionnaire'])
        ->middleware('permission:cv.create-questionnaire');
});
