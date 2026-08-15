<?php

use App\Domains\Questionnaire\Controllers\QuestionnaireController;
use App\Domains\Questionnaire\Controllers\QuestionnaireDocumentController;
use App\Domains\Questionnaire\Controllers\QuestionnaireManagementController;
use App\Http\Controllers\GrantAccessController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('questionnaire/init', [QuestionnaireController::class, 'init'])
    ->middleware('throttle:10,1');
Route::post('questionnaire/verify-init-otp', [QuestionnaireController::class, 'verifyInitOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// OTP endpoints
Route::post('questionnaire/pending/{uuid}/send-otp', [QuestionnaireController::class, 'resendInitOtp'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('questionnaire/{uuid}/send-mobile-otp', [QuestionnaireController::class, 'sendMobileOtp'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('questionnaire/{uuid}/send-email-otp', [QuestionnaireController::class, 'sendEmailOtp'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('questionnaire/{uuid}/verify-mobile-otp', [QuestionnaireController::class, 'verifyMobileOtp'])
    ->middleware('throttle:questionnaire-otp-verify');
Route::post('questionnaire/{uuid}/verify-email-otp', [QuestionnaireController::class, 'verifyEmailOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// Grant issuance (public, OTP-gated)
Route::post('{entity}/{uuid}/request-access', [GrantAccessController::class, 'requestAccess'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('{entity}/{uuid}/verify-access-otp', [GrantAccessController::class, 'verifyAccessOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// Existence check (public, no OTP/rate limit) so clients can show a 404 before
// offering the protected access form.
Route::get('{entity}/{uuid}/exists', [GrantAccessController::class, 'exists']);

// Per-category document requirements for the questionnaire flow (public —
// no sensitive data). Registered before the {uuid} routes so it never binds
// to the questionnaire show route.
Route::get('questionnaire/document-requirements', [QuestionnaireDocumentController::class, 'requirements']);

// Read-only access (grant.access:questionnaire,view)
Route::middleware('grant.access:questionnaire,view')->group(function () {
    Route::get('questionnaire/{uuid}', [QuestionnaireController::class, 'show']);
    Route::get('questionnaire/{uuid}/documents', [QuestionnaireDocumentController::class, 'index']);
});

// Editing access (grant.access:questionnaire,edit)
Route::middleware('grant.access:questionnaire,edit')->group(function () {
    Route::put('questionnaire/{uuid}/sections/{section}', [QuestionnaireController::class, 'saveSection']);
    Route::post('questionnaire/{uuid}/submit', [QuestionnaireController::class, 'submit']);
    Route::post('questionnaire/{uuid}/documents', [QuestionnaireDocumentController::class, 'store'])
        ->middleware('throttle:30,1');
    Route::delete('questionnaire/{uuid}/documents/{usageId}', [QuestionnaireDocumentController::class, 'destroy']);
});

// Signed document serving stays public because <img> can't send headers.
Route::get('questionnaire/documents/{uuid}/serve', [QuestionnaireDocumentController::class, 'serve'])
    ->name('questionnaire.documents.serve')
    ->middleware('signed:relative,thumbnail');

// Protected routes (HR management)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('questionnaires', [QuestionnaireManagementController::class, 'index']);
    Route::get('questionnaires/{questionnaire}', [QuestionnaireManagementController::class, 'show']);
    Route::post('questionnaire/{uuid}/review', [QuestionnaireController::class, 'review']);
    Route::post('questionnaire/{uuid}/reject', [QuestionnaireController::class, 'reject']);
});
