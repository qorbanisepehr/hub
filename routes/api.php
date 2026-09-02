<?php

use App\Http\Controllers\GrantAccessController;
use Illuminate\Support\Facades\Route;

// Generic grant issuance (public, OTP-gated) for any entity in
// config/grants.php. Registered once here instead of being duplicated across
// domain route files; `{entity}` resolves via GrantAccessController/Resolver.
Route::post('{entity}/{uuid}/request-access', [GrantAccessController::class, 'requestAccess'])
    ->middleware('throttle:questionnaire-otp-send');
Route::post('{entity}/{uuid}/verify-access-otp', [GrantAccessController::class, 'verifyAccessOtp'])
    ->middleware('throttle:questionnaire-otp-verify');

// Existence check (public, no OTP/rate limit) so clients can show a 404
// before offering the protected access form.
Route::get('{entity}/{uuid}/exists', [GrantAccessController::class, 'exists']);

require base_path('app/Domains/Auth/routes/api.php');
require base_path('app/Domains/Employee/routes/api.php');
require base_path('app/Domains/Document/routes/api.php');
require base_path('app/Domains/Authorization/routes/api.php');
require base_path('app/Domains/Questionnaire/routes/api.php');
require base_path('app/Domains/Cv/routes/api.php');
require base_path('app/Domains/Settings/routes/api.php');
require base_path('app/Domains/FormOptions/routes/api.php');
require base_path('app/Domains/Audit/routes/api.php');
