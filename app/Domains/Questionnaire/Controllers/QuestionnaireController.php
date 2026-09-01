<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Http\Concerns\InitiatesCandidate;
use App\Http\Requests\Candidate\SubmitSectionRequest;
use App\Http\Requests\Candidate\VerifyOtpCodeRequest;
use App\Http\Responses\OtpResponder;
use App\Models\PendingVerification;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
use App\Support\MobileNumber;
use App\Support\ValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuestionnaireController extends Controller
{
    use InitiatesCandidate;
    use OtpResponder;

    public function __construct(
        private QuestionnaireService $questionnaireService,
        private OtpService $otpService,
        private Authorization $authorization,
        private SessionGrantStore $sessionGrants,
    ) {}

    protected function candidateType(): string
    {
        return 'questionnaire';
    }

    protected function candidateModel(): string
    {
        return Questionnaire::class;
    }

    protected function candidateService(): object
    {
        return $this->questionnaireService;
    }

    protected function candidateResource(mixed $record): mixed
    {
        return new QuestionnaireResource($record);
    }

    protected function candidateIsFinalized(mixed $record): bool
    {
        return $record instanceof Questionnaire && $record->isReviewed();
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    public function saveSection(string $uuid, string $section, SubmitSectionRequest $request): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.only_draft_editable'),
            ], 422);
        }

        $data = $request->validated();

        $questionnaire = $this->questionnaireService->saveSection($questionnaire, $section, $data);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('questionnaire.questionnaire.saved'),
        ]);
    }

    public function resendInitOtp(string $uuid): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $uuid)->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('questionnaire.questionnaire.already_verified')], 422);
        }

        $status = $this->otpService->sendWithCooldown($pending, 'mobile', OtpContext::Register);

        return $this->respondToSend($pending, 'mobile', OtpContext::Register, $status);
    }

    public function sendMobileOtp(string $uuid, Request $request): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $data = $request->validate([
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
        ]);

        $pendingMobile = isset($data['mobile']) ? MobileNumber::normalize($data['mobile']) : null;

        $status = $this->otpService->sendWithCooldown($questionnaire, 'mobile', OtpContext::VerifyMobile);

        if ($pendingMobile !== null && $status === OtpSendStatus::Sent) {
            $this->otpService->storePendingValue($questionnaire, 'mobile', OtpContext::VerifyMobile, $pendingMobile);
        }

        return $this->respondToSend($questionnaire, 'mobile', OtpContext::VerifyMobile, $status);
    }

    public function sendEmailOtp(string $uuid, Request $request): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $data = $request->validate([
            'email' => 'nullable|email|max:255',
        ]);

        $pendingEmail = $data['email'] ?? null;

        $status = $this->otpService->sendWithCooldown($questionnaire, 'email', OtpContext::VerifyEmail);

        if ($pendingEmail !== null && $status === OtpSendStatus::Sent) {
            $this->otpService->storePendingValue($questionnaire, 'email', OtpContext::VerifyEmail, $pendingEmail);
        }

        return $this->respondToSend($questionnaire, 'email', OtpContext::VerifyEmail, $status);
    }

    public function verifyMobileOtp(VerifyOtpCodeRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $status = $this->otpService->attemptVerification($questionnaire, 'mobile', OtpContext::VerifyMobile, $request->validated('otp'));

        if ($response = $this->respondToVerification($questionnaire, 'mobile', OtpContext::VerifyMobile, $status)) {
            return $response;
        }

        $pendingMobile = $this->otpService->pullPendingValue($questionnaire, 'mobile', OtpContext::VerifyMobile);

        if ($pendingMobile !== null && $pendingMobile !== $questionnaire->mobile) {
            $questionnaire->update(['mobile' => $pendingMobile]);
        }

        return $this->otpVerifiedResponse($questionnaire, 'mobile');
    }

    public function verifyEmailOtp(VerifyOtpCodeRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $status = $this->otpService->attemptVerification($questionnaire, 'email', OtpContext::VerifyEmail, $request->validated('otp'));

        if ($response = $this->respondToVerification($questionnaire, 'email', OtpContext::VerifyEmail, $status)) {
            return $response;
        }

        $pendingEmail = $this->otpService->pullPendingValue($questionnaire, 'email', OtpContext::VerifyEmail);

        if ($pendingEmail !== null && $pendingEmail !== $questionnaire->email) {
            $questionnaire->update(['email' => $pendingEmail]);
        }

        return $this->otpVerifiedResponse($questionnaire, 'email');
    }

    public function submit(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.only_draft_submittable'),
            ], 422);
        }

        if (! $questionnaire->isFullyVerified()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.not_verified'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->submit($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('questionnaire.questionnaire.submitted'),
        ]);
    }

    public function review(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $this->authorization->authorize($request->user(), 'questionnaire.review', $questionnaire);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.only_submitted_reviewable'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->review($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('questionnaire.questionnaire.reviewed'),
        ]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findByUuidOrFail($uuid);

        $this->authorization->authorize($request->user(), 'questionnaire.reject', $questionnaire);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.only_submitted_rejectable'),
            ], 422);
        }

        $questionnaire = $this->questionnaireService->reject($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('questionnaire.questionnaire.rejected'),
        ]);
    }
}
