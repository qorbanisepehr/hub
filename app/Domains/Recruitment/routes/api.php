<?php

use App\Domains\Recruitment\Controllers\QuestionnaireController;
use App\Domains\Recruitment\Controllers\QuestionnaireDocumentController;
use App\Domains\Recruitment\Controllers\RecruitmentController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('questionnaire/init', [QuestionnaireController::class, 'init'])
    ->middleware('throttle:10,1');
Route::post('questionnaire/verify-init-otp', [QuestionnaireController::class, 'verifyInitOtp'])
    ->middleware('throttle:recruitment-otp-verify');

Route::get('questionnaire/{uuid}', [QuestionnaireController::class, 'show']);

// Section-based save (new API)
Route::put('questionnaire/{uuid}/sections/{section}', [QuestionnaireController::class, 'saveSection']);

// OTP endpoints
Route::post('questionnaire/pending/{uuid}/send-otp', [QuestionnaireController::class, 'resendInitOtp'])
    ->middleware('throttle:recruitment-otp-send');
Route::post('questionnaire/{uuid}/send-mobile-otp', [QuestionnaireController::class, 'sendMobileOtp'])
    ->middleware('throttle:recruitment-otp-send');
Route::post('questionnaire/{uuid}/send-email-otp', [QuestionnaireController::class, 'sendEmailOtp'])
    ->middleware('throttle:recruitment-otp-send');
Route::post('questionnaire/{uuid}/verify-mobile-otp', [QuestionnaireController::class, 'verifyMobileOtp'])
    ->middleware('throttle:recruitment-otp-verify');
Route::post('questionnaire/{uuid}/verify-email-otp', [QuestionnaireController::class, 'verifyEmailOtp'])
    ->middleware('throttle:recruitment-otp-verify');

// Submit (public, self-service)
Route::post('questionnaire/{uuid}/submit', [QuestionnaireController::class, 'submit']);

// Questionnaire document routes (public, throttled)
Route::get('questionnaire/{uuid}/documents', [QuestionnaireDocumentController::class, 'index']);
Route::post('questionnaire/{uuid}/documents', [QuestionnaireDocumentController::class, 'store'])
    ->middleware('throttle:30,1');
Route::delete('questionnaire/{uuid}/documents/{usageId}', [QuestionnaireDocumentController::class, 'destroy']);
Route::get('questionnaire/documents/{uuid}/serve', [QuestionnaireDocumentController::class, 'serve'])
    ->name('questionnaire.documents.serve')
    ->middleware('signed:thumbnail');

// Protected routes (HR management)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('recruitment/questionnaires', [RecruitmentController::class, 'index']);
    Route::get('recruitment/questionnaires/{questionnaire}', [RecruitmentController::class, 'show']);
    Route::post('questionnaire/{uuid}/review', [QuestionnaireController::class, 'review']);
    Route::post('questionnaire/{uuid}/reject', [QuestionnaireController::class, 'reject']);
});
