<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Recruitment\Guards\OtpGuard;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\InitQuestionnaireRequest;
use App\Domains\Recruitment\Requests\SectionSaveRequest;
use App\Domains\Recruitment\Requests\VerifyInitOtpRequest;
use App\Domains\Recruitment\Requests\VerifyQuestionnaireRequest;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use App\Domains\Recruitment\Services\QuestionnaireService;
use App\Models\PendingVerification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

class QuestionnaireController extends Controller
{
    use OtpGuard;

    public function __construct(
        private QuestionnaireService $questionnaireService,
        OtpService $otpService,
    ) {
        $this->otpService = $otpService;
    }

    public function init(InitQuestionnaireRequest $request): JsonResponse
    {
        $data = $request->validated();

        $existing = PendingVerification::query()
            ->where('type', 'questionnaire')
            ->where('mobile', $data['mobile'])
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($existing) {
            $existing->update([
                'email' => $data['email'],
                'payload' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                ],
            ]);

            if ($this->otpService->isExpired($existing, 'mobile')) {
                if ($this->otpService->isLocked($existing, 'mobile')) {
                    return $this->otpLockedResponse($existing, 'mobile');
                }

                $this->otpService->send($existing, 'mobile');

                return response()->json([
                    'data' => ['uuid' => $existing->uuid],
                    'requires_otp' => true,
                    'code_sent' => true,
                    'expires_in' => $this->otpService->getExpiresIn($existing, 'mobile'),
                    'message' => __('recruitment.questionnaire.otp_sent'),
                ]);
            }

            return response()->json([
                'data' => ['uuid' => $existing->uuid],
                'requires_otp' => true,
                'code_sent' => false,
                'expires_in' => $this->otpService->getExpiresIn($existing, 'mobile'),
                'message' => __('recruitment.questionnaire.otp_already_sent'),
            ]);
        }

        $pending = PendingVerification::create([
            'type' => 'questionnaire',
            'mobile' => $data['mobile'],
            'email' => $data['email'],
            'payload' => [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
            ],
        ]);

        $this->otpService->send($pending, 'mobile');

        return response()->json([
            'data' => ['uuid' => $pending->uuid],
            'requires_otp' => true,
            'code_sent' => true,
            'expires_in' => $this->otpService->getExpiresIn($pending, 'mobile'),
            'message' => __('recruitment.questionnaire.otp_sent'),
        ], 201);
    }

    public function verifyInitOtp(VerifyInitOtpRequest $request): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $request->validated('uuid'))->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('recruitment.questionnaire.already_verified')], 422);
        }

        if ($errorResponse = $this->attemptOtpVerification($pending, 'mobile', $request->validated('otp'))) {
            return $errorResponse;
        }

        $this->otpService->clearFailedAttempts($pending, 'mobile');

        // Check for existing questionnaire with this mobile
        $existing = Questionnaire::where('mobile', $pending->mobile)->first();

        if ($existing && ! $existing->isReviewed() && $existing->isSubmitted()) {
            $existing->update(['status' => 'draft']);
        }

        // Only re-apply the start-form payload to questionnaires that are
        // still editable. Reviewed records must not be silently mutated.
        $updateData = $existing && ! $existing->isReviewed()
            ? Arr::only($pending->payload, [
                'first_name', 'last_name', 'email', 'mobile',
            ])
            : [];

        if ($existing && $updateData) {
            // Reset verification if contact data changed
            if (isset($updateData['email']) && $updateData['email'] !== $existing->email) {
                $updateData['email_verified_at'] = null;
            }
            if (isset($updateData['mobile']) && $updateData['mobile'] !== $existing->mobile) {
                $updateData['mobile_verified_at'] = null;
            }
            $existing->update($updateData);
        }

        $questionnaire = $existing ?? $this->questionnaireService->create($pending->payload);

        $questionnaire->markOtpVerified('mobile');

        $pending->delete();

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => $existing
                ? __('recruitment.questionnaire.verified')
                : __('recruitment.questionnaire.created'),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    public function saveSection(string $uuid, string $section, SectionSaveRequest $request): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.only_draft_editable'),
            ], 422);
        }

        $data = $request->validated();

        $questionnaire = $this->questionnaireService->saveSection($questionnaire, $section, $data);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.saved'),
        ]);
    }

    public function resendInitOtp(string $uuid): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $uuid)->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('recruitment.questionnaire.already_verified')], 422);
        }

        if ($response = $this->sendOtpWithLockoutCheck($pending, 'mobile')) {
            return $response;
        }

        return $this->otpSentResponse($pending, 'mobile');
    }

    public function sendMobileOtp(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if ($response = $this->sendOtpWithLockoutCheck($questionnaire, 'mobile')) {
            return $response;
        }

        return $this->otpSentResponse($questionnaire, 'mobile');
    }

    public function sendEmailOtp(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if ($response = $this->sendOtpWithLockoutCheck($questionnaire, 'email')) {
            return $response;
        }

        return $this->otpSentResponse($questionnaire, 'email');
    }

    public function verifyMobileOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if ($errorResponse = $this->attemptOtpVerification($questionnaire, 'mobile', $request->validated('otp'))) {
            return $errorResponse;
        }

        return $this->otpVerifiedResponse($questionnaire, 'mobile');
    }

    public function verifyEmailOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if ($errorResponse = $this->attemptOtpVerification($questionnaire, 'email', $request->validated('otp'))) {
            return $errorResponse;
        }

        return $this->otpVerifiedResponse($questionnaire, 'email');
    }

    public function submit(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.only_draft_submittable'),
            ], 422);
        }

        if (! $questionnaire->isFullyVerified()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.not_verified'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->submit($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.submitted'),
        ]);
    }

    public function review(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.only_submitted_reviewable'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->updateStatus($questionnaire, 'reviewed');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.reviewed'),
        ]);
    }

    public function reject(string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.only_submitted_rejectable'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->updateStatus($questionnaire, 'draft');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.rejected'),
        ]);
    }
}
