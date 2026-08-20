<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Requests\InitQuestionnaireRequest;
use App\Domains\Questionnaire\Requests\SectionSaveRequest;
use App\Domains\Questionnaire\Requests\VerifyInitOtpRequest;
use App\Domains\Questionnaire\Requests\VerifyQuestionnaireRequest;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Http\Responses\OtpResponder;
use App\Models\PendingVerification;
use App\Services\OtpService;
use App\Support\MobileNumber;
use App\Support\ValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

class QuestionnaireController extends Controller
{
    use OtpResponder;

    public function __construct(
        private QuestionnaireService $questionnaireService,
        private OtpService $otpService,
        private Authorization $authorization,
        private AuditEventDispatcher $audit,
    ) {}

    public function init(InitQuestionnaireRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['mobile'] = MobileNumber::normalize($data['mobile']);

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
        ];

        $existing = PendingVerification::query()
            ->where('type', 'questionnaire')
            ->where('mobile', $data['mobile'])
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($existing) {
            $existing->update([
                'email' => $data['email'],
                'payload' => $payload,
            ]);
        }

        $pending = $existing ?? PendingVerification::create([
            'type' => 'questionnaire',
            'mobile' => $data['mobile'],
            'email' => $data['email'],
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
                ? __('questionnaire.questionnaire.otp_sent')
                : __('questionnaire.questionnaire.otp_already_sent'),
        ], $existing ? 200 : 201);
    }

    public function verifyInitOtp(VerifyInitOtpRequest $request): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $request->validated('uuid'))->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('questionnaire.questionnaire.already_verified')], 422);
        }

        $status = $this->otpService->attemptVerification($pending, 'mobile', OtpContext::Register, $request->validated('otp'));

        if ($response = $this->respondToVerification($pending, 'mobile', OtpContext::Register, $status)) {
            return $response;
        }

        // Check for existing questionnaire with this mobile
        $existing = Questionnaire::where('mobile', $pending->mobile)->first();

        if ($existing && ! $existing->isReviewed() && $existing->isSubmitted()) {
            $existing->update(['status' => 'draft']);
        }

        // For existing questionnaires only re-apply contact info so the user
        // can re-access after changing email/mobile. Names are set at creation
        // and edited inside the wizard, so they must not be overwritten here
        // (which would silently revert wizard edits). Reviewed records must
        // not be silently mutated at all.
        $updateData = $existing && ! $existing->isReviewed()
            ? Arr::only($pending->payload, ['email', 'mobile'])
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

        $token = $this->otpService->issueGrant($questionnaire, 'mobile', OtpContext::AccessProtected, GrantPurpose::Edit);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'access_token' => $token,
            'expires_in' => $this->otpService->getGrantExpiresIn(GrantPurpose::Edit),
            'message' => $existing
                ? __('questionnaire.questionnaire.verified')
                : __('questionnaire.questionnaire.created'),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    public function saveSection(string $uuid, string $section, SectionSaveRequest $request): JsonResponse
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

    public function verifyMobileOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
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

    public function verifyEmailOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
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

        $this->audit->record(new QuestionnaireSubmitted($questionnaire));

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

        $questionnaire = $this->questionnaireService->updateStatus($questionnaire, 'reviewed');

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

        $questionnaire = $this->questionnaireService->updateStatus($questionnaire, 'draft');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('questionnaire.questionnaire.rejected'),
        ]);
    }
}
