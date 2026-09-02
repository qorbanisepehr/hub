<?php

namespace App\Http\Concerns;

use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Http\Requests\Candidate\InitCandidateRequest;
use App\Http\Requests\Candidate\VerifyInitOtpRequest;
use App\Models\PendingVerification;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
use App\Support\MobileNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * Shared public candidate `init` -> `verify-init-otp` sequence for grant
 * entities whose draft record is created from a PendingVerification (Cv and
 * Questionnaire).
 *
 * Consuming controllers supply per-entity metadata (type key, model, service,
 * resource, and the "finalized" predicate - e.g. approved vs reviewed) and
 * already provide `OtpResponder` plus `OtpService`/`SessionGrantStore`.
 *
 * @property OtpService $otpService
 * @property SessionGrantStore $sessionGrants
 *
 * @method \Illuminate\Http\JsonResponse otpLockedResponse(\App\Contracts\OtpVerifiable $verifiable, string $channel, \App\Enums\OtpContext $context)
 * @method \Illuminate\Http\JsonResponse|null respondToVerification(\App\Contracts\OtpVerifiable $verifiable, string $channel, \App\Enums\OtpContext $context, \App\Enums\OtpVerifyStatus $status)
 * @method string otpLang(string $key, array $replace = [])
 */
trait InitiatesCandidate
{
    /** Grant entity key, e.g. `cv` or `questionnaire`. */
    abstract protected function candidateType(): string;

    /** @return class-string */
    abstract protected function candidateModel(): string;

    /** Service exposing `create(array $baseData)`. */
    abstract protected function candidateService(): object;

    abstract protected function candidateResource(mixed $record): mixed;

    /** Whether the record is in a finalized state that must not be reopened. */
    abstract protected function candidateIsFinalized(mixed $record): bool;

    public function init(InitCandidateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['mobile'] = MobileNumber::normalize($data['mobile']);

        $payload = Arr::only($data, ['first_name', 'last_name', 'email', 'mobile']);

        $existing = PendingVerification::query()
            ->where('type', $this->candidateType())
            ->where('mobile', $data['mobile'])
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($existing) {
            $existing->update([
                'email' => $data['email'] ?? null,
                'payload' => $payload,
            ]);
        }

        $pending = $existing ?? PendingVerification::create([
            'type' => $this->candidateType(),
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'payload' => $payload,
        ]);

        $status = $this->otpService->sendWithCooldown($pending, 'mobile', OtpContext::Register);

        if ($status === OtpSendStatus::Locked) {
            return $this->otpLockedResponse($pending, 'mobile', OtpContext::Register);
        }

        return response()->json([
            'data' => ['uuid' => $pending->uuid],
            'requires_otp' => true,
            'code_sent' => $status === OtpSendStatus::Sent,
            'expires_in' => $this->otpService->getExpiresIn($pending, 'mobile', OtpContext::Register),
            'message' => $status === OtpSendStatus::Sent
                ? $this->otpLang('otp_sent')
                : $this->otpLang('otp_already_sent'),
        ], $existing ? 200 : 201);
    }

    public function verifyInitOtp(VerifyInitOtpRequest $request): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $request->validated('uuid'))->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => $this->otpLang('already_verified')], 422);
        }

        $status = $this->otpService->attemptVerification($pending, 'mobile', OtpContext::Register, $request->validated('otp'));

        if ($response = $this->respondToVerification($pending, 'mobile', OtpContext::Register, $status)) {
            return $response;
        }

        // Check for an existing record with this mobile.
        $model = $this->candidateModel();
        $existing = $model::where('mobile', $pending->mobile)->first();

        $finalized = $this->candidateIsFinalized($existing);

        if ($existing && ! $finalized && $existing->isSubmitted()) {
            $existing->update(['status' => 'draft']);
        }

        // For existing records only re-apply contact info so the user can
        // re-access after changing email/mobile. Names are set at creation
        // and edited inside the wizard, so they must not be overwritten here.
        // Finalized records must not be silently mutated at all.
        $updateData = $existing && ! $finalized
            ? Arr::only($pending->payload, ['email', 'mobile'])
            : [];

        if ($existing && $updateData) {
            // Reset verification if contact data changed.
            if (isset($updateData['email']) && $updateData['email'] !== null && $updateData['email'] !== $existing->email) {
                $updateData['email_verified_at'] = null;
            }
            if (isset($updateData['mobile']) && $updateData['mobile'] !== null && $updateData['mobile'] !== $existing->mobile) {
                $updateData['mobile_verified_at'] = null;
            }
            $existing->update($updateData);
        }

        $record = $existing ?? $this->candidateService()->create($pending->payload);

        $record->markOtpVerified('mobile');

        $pending->delete();

        $token = $this->otpService->issueGrant($record, 'mobile', OtpContext::AccessProtected, GrantPurpose::Edit);

        // Bind the grant to the session so header-less browser requests
        // (<img>, embed) can serve documents via their cookie.
        $this->sessionGrants->remember($record->getOtpIdentifier(), $token);

        return response()->json([
            'data' => $this->candidateResource($record->fresh()),
            'access_token' => $token,
            'expires_in' => $this->otpService->getGrantExpiresIn(GrantPurpose::Edit),
            'message' => $existing ? $this->otpLang('verified') : $this->otpLang('created'),
        ]);
    }
}
