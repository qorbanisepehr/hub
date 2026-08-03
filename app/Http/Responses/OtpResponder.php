<?php

namespace App\Http\Responses;

use App\Contracts\OtpVerifiable;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Enums\OtpVerifyStatus;
use Illuminate\Http\JsonResponse;

/**
 * Shared OTP send/verify response builders.
 *
 * Consuming controllers must inject an `OtpService` into the trait's calls
 * by promoting a private `OtpService $otpService` constructor property.
 * Copy strings resolve through `otpLang()`, which defaults to
 * `recruitment.questionnaire.*` and may be overridden.
 */
trait OtpResponder
{
    /**
     * Map an OTP verification outcome to an HTTP response.
     * Returns null when verification succeeded.
     */
    protected function respondToVerification(
        OtpVerifiable $verifiable,
        string $channel,
        OtpContext $context,
        OtpVerifyStatus $status,
    ): ?JsonResponse {
        return match ($status) {
            OtpVerifyStatus::Locked => $this->otpLockedResponse($verifiable, $channel, $context),
            OtpVerifyStatus::Expired => $this->otpExpiredResponse(),
            OtpVerifyStatus::Invalid => $this->otpInvalidResponse(),
            OtpVerifyStatus::Success => null,
        };
    }

    /**
     * Map an OTP send outcome to a unified `{ message, code_sent, expires_in }`
     * response. Callers may append extra keys (e.g. `destination`).
     */
    protected function respondToSend(
        OtpVerifiable $verifiable,
        string $channel,
        OtpContext $context,
        OtpSendStatus $status,
    ): JsonResponse {
        return match ($status) {
            OtpSendStatus::Sent => $this->otpSentResponse($verifiable, $channel, $context),
            OtpSendStatus::AlreadySent => $this->otpAlreadySentResponse($verifiable, $channel, $context),
            OtpSendStatus::Locked => $this->otpLockedResponse($verifiable, $channel, $context),
        };
    }

    protected function otpLockedResponse(
        OtpVerifiable $verifiable,
        string $channel,
        OtpContext $context,
    ): JsonResponse {
        $seconds = $this->otpService->getLockoutSeconds($verifiable, $channel, $context);

        return response()->json([
            'message' => $this->otpLang('otp_locked', ['seconds' => $seconds]),
            'retry_after' => $seconds,
        ], 429);
    }

    protected function otpExpiredResponse(): JsonResponse
    {
        return response()->json(['message' => $this->otpLang('otp_expired')], 422);
    }

    protected function otpInvalidResponse(): JsonResponse
    {
        return response()->json(['message' => $this->otpLang('otp_invalid')], 422);
    }

    protected function otpSentResponse(
        OtpVerifiable $verifiable,
        string $channel,
        OtpContext $context,
    ): JsonResponse {
        return response()->json([
            'message' => $this->otpLang('otp_sent'),
            'code_sent' => true,
            'expires_in' => $this->otpService->getExpiresIn($verifiable, $channel, $context),
        ]);
    }

    protected function otpAlreadySentResponse(
        OtpVerifiable $verifiable,
        string $channel,
        OtpContext $context,
    ): JsonResponse {
        return response()->json([
            'message' => $this->otpLang('otp_already_sent'),
            'code_sent' => false,
            'expires_in' => $this->otpService->getExpiresIn($verifiable, $channel, $context),
        ]);
    }

    /**
     * Mark the channel verified and return a plain `{ message }` success
     * response. Callers attach resources to it.
     */
    protected function otpVerifiedResponse(OtpVerifiable $verifiable, string $channel): JsonResponse
    {
        $verifiable->markOtpVerified($channel);

        return response()->json(['message' => $this->otpLang('verified')]);
    }

    /**
     * Resolve an OTP copy string. Override in controllers using a different
     * lang namespace (e.g. `auth.*`).
     *
     * @param  array<string, int|string>  $replace
     */
    protected function otpLang(string $key, array $replace = []): string
    {
        return __("recruitment.questionnaire.{$key}", $replace);
    }
}
