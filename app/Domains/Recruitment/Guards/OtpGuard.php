<?php

namespace App\Domains\Recruitment\Guards;

use App\Contracts\OtpVerifiable;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use App\Services\OtpService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait OtpGuard
{
    protected OtpService $otpService;

    protected function otpLockedResponse(OtpVerifiable $verifiable, string $channel): JsonResponse
    {
        $seconds = $this->otpService->getLockoutSeconds($verifiable, $channel);

        return response()->json([
            'message' => __('recruitment.questionnaire.otp_locked', ['seconds' => $seconds]),
            'retry_after' => $seconds,
        ], 429);
    }

    protected function otpExpiredResponse(string $channel): JsonResponse
    {
        return response()->json([
            'message' => __('recruitment.questionnaire.otp_expired'),
        ], 422);
    }

    protected function otpInvalidResponse(string $channel): JsonResponse
    {
        return response()->json([
            'message' => __('recruitment.questionnaire.otp_invalid'),
        ], 422);
    }

    protected function otpSentResponse(OtpVerifiable $verifiable, string $channel): JsonResponse
    {
        return response()->json([
            'message' => __('recruitment.questionnaire.otp_sent'),
            'code_sent' => true,
            'expires_in' => $this->otpService->getExpiresIn($verifiable, $channel),
        ]);
    }

    protected function otpAlreadySentResponse(OtpVerifiable $verifiable, string $channel): JsonResponse
    {
        return response()->json([
            'message' => __('recruitment.questionnaire.otp_already_sent'),
            'code_sent' => false,
            'expires_in' => $this->otpService->getExpiresIn($verifiable, $channel),
        ]);
    }

    protected function otpVerifiedResponse(OtpVerifiable $verifiable, string $channel, bool $isInit = false): JsonResponse
    {
        $verifiable->markOtpVerified($channel);
        $this->otpService->clearFailedAttempts($verifiable, $channel);

        return response()->json([
            'data' => $verifiable instanceof Model
                ? new QuestionnaireResource($verifiable->fresh())
                : null,
            'message' => __('recruitment.questionnaire.verified'),
        ]);
    }

    /**
     * Attempt to verify OTP. Returns JsonResponse on failure, null on success.
     */
    protected function attemptOtpVerification(OtpVerifiable $verifiable, string $channel, string $otp): ?JsonResponse
    {
        if ($this->otpService->isLocked($verifiable, $channel)) {
            return $this->otpLockedResponse($verifiable, $channel);
        }

        if ($this->otpService->isExpired($verifiable, $channel)) {
            return $this->otpExpiredResponse($channel);
        }

        if (! $this->otpService->verify($verifiable, $channel, $otp)) {
            $this->otpService->hitFailedAttempt($verifiable, $channel);

            return $this->otpInvalidResponse($channel);
        }

        return null;
    }

    /**
     * Send OTP with lockout guard. Returns JsonResponse on lockout, null on success.
     */
    protected function sendOtpWithLockoutCheck(OtpVerifiable $verifiable, string $channel): ?JsonResponse
    {
        if (! $this->otpService->isExpired($verifiable, $channel)) {
            return $this->otpAlreadySentResponse($verifiable, $channel);
        }

        if ($this->otpService->isLocked($verifiable, $channel)) {
            return $this->otpLockedResponse($verifiable, $channel);
        }

        $this->otpService->send($verifiable, $channel);

        return null;
    }
}
