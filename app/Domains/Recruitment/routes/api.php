<?php

use App\Domains\Recruitment\Controllers\QuestionnaireController;
use App\Domains\Recruitment\Controllers\RecruitmentController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('questionnaire/init', [QuestionnaireController::class, 'init'])
    ->middleware('throttle:10,1');
Route::get('questionnaire/{uuid}', [QuestionnaireController::class, 'show']);
Route::put('questionnaire/{uuid}', [QuestionnaireController::class, 'save']);
Route::post('questionnaire/{uuid}/send-mobile-otp', [QuestionnaireController::class, 'sendMobileOtp'])
    ->middleware('throttle:5,1');
Route::post('questionnaire/{uuid}/send-email-otp', [QuestionnaireController::class, 'sendEmailOtp'])
    ->middleware('throttle:5,1');
Route::post('questionnaire/{uuid}/verify-mobile-otp', [QuestionnaireController::class, 'verifyMobileOtp']);
Route::post('questionnaire/{uuid}/verify-email-otp', [QuestionnaireController::class, 'verifyEmailOtp']);
Route::post('questionnaire/{uuid}/submit', [QuestionnaireController::class, 'submit']);

// Protected routes (HR management)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('recruitment/questionnaires', [RecruitmentController::class, 'index']);
    Route::get('recruitment/questionnaires/{questionnaire}', [RecruitmentController::class, 'show']);
});
