<?php

namespace App\Domains\Cv\Controllers;

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Requests\InitCvRequest;
use App\Domains\Cv\Requests\RejectCvRequest;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
use App\Domains\Recruitment\Requests\SectionSaveRequest;
use App\Domains\Recruitment\Requests\VerifyInitOtpRequest;
use App\Domains\Recruitment\Requests\VerifyQuestionnaireRequest;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Http\Responses\OtpResponder;
use App\Models\PendingVerification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

class CvController extends Controller
{
    use OtpResponder;

    public function __construct(
        private CvService $cvService,
        private OtpService $otpService,
    ) {}

    public function init(InitCvRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payload = Arr::only($data, ['first_name', 'last_name', 'email', 'mobile']);

        $existing = PendingVerification::query()
            ->where('type', 'cv')
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
            'type' => 'cv',
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
                ? __('cv.otp_sent')
                : __('cv.otp_already_sent'),
        ], $existing ? 200 : 201);
    }

    public function verifyInitOtp(VerifyInitOtpRequest $request): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $request->validated('uuid'))->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('cv.already_verified')], 422);
        }

        $status = $this->otpService->attemptVerification($pending, 'mobile', OtpContext::Register, $request->validated('otp'));

        if ($response = $this->respondToVerification($pending, 'mobile', OtpContext::Register, $status)) {
            return $response;
        }

        // Check for an existing CV with this mobile
        $existing = Cv::where('mobile', $pending->mobile)->first();

        if ($existing && ! $existing->isReviewed() && $existing->isSubmitted()) {
            $existing->update(['status' => 'draft']);
        }

        // For existing CVs only re-apply contact info so the user can re-access
        // after changing email/mobile. Names are set at creation and edited
        // inside the wizard, so they must not be overwritten here. Reviewed
        // records must not be silently mutated at all.
        $updateData = $existing && ! $existing->isReviewed()
            ? Arr::only($pending->payload, ['email', 'mobile'])
            : [];

        if ($existing && $updateData) {
            if (($updateData['email'] ?? null) !== null && $updateData['email'] !== $existing->email) {
                $updateData['email_verified_at'] = null;
            }
            if (($updateData['mobile'] ?? null) !== null && $updateData['mobile'] !== $existing->mobile) {
                $updateData['mobile_verified_at'] = null;
            }
            $existing->update($updateData);
        }

        $cv = $existing ?? $this->cvService->create($pending->payload);

        $cv->markOtpVerified('mobile');

        $pending->delete();

        $token = $this->otpService->issueGrant($cv, 'mobile', OtpContext::AccessProtected, GrantPurpose::Edit);

        return response()->json([
            'data' => new CvResource($cv->fresh()),
            'access_token' => $token,
            'expires_in' => $this->otpService->getGrantExpiresIn(GrantPurpose::Edit),
            'message' => $existing
                ? __('cv.verified')
                : __('cv.created'),
        ]);
    }

    public function resendInitOtp(string $uuid): JsonResponse
    {
        $pending = PendingVerification::where('uuid', $uuid)->firstOrFail();

        if ($pending->isVerified()) {
            return response()->json(['message' => __('cv.already_verified')], 422);
        }

        $status = $this->otpService->sendWithCooldown($pending, 'mobile', OtpContext::Register);

        return $this->respondToSend($pending, 'mobile', OtpContext::Register, $status);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        return response()->json([
            'data' => new CvResource($cv),
        ]);
    }

    public function saveSection(string $uuid, string $section, SectionSaveRequest $request): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        if (! $cv->isDraft()) {
            return response()->json([
                'message' => __('cv.only_draft_editable'),
            ], 422);
        }

        $cv = $this->cvService->saveSection($cv, $section, $request->validated());

        return response()->json([
            'data' => new CvResource($cv),
            'message' => __('cv.saved'),
        ]);
    }

    public function sendMobileOtp(string $uuid, Request $request): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $data = $request->validate([
            'mobile' => 'nullable|string|max:15|regex:/^09\d{9}$/',
        ]);

        $pendingMobile = $data['mobile'] ?? null;

        $status = $this->otpService->sendWithCooldown($cv, 'mobile', OtpContext::VerifyMobile);

        if ($pendingMobile !== null && $status === OtpSendStatus::Sent) {
            $this->otpService->storePendingValue($cv, 'mobile', OtpContext::VerifyMobile, $pendingMobile);
        }

        return $this->respondToSend($cv, 'mobile', OtpContext::VerifyMobile, $status);
    }

    public function sendEmailOtp(string $uuid, Request $request): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $data = $request->validate([
            'email' => 'nullable|email|max:255',
        ]);

        $pendingEmail = $data['email'] ?? null;

        $status = $this->otpService->sendWithCooldown($cv, 'email', OtpContext::VerifyEmail);

        if ($pendingEmail !== null && $status === OtpSendStatus::Sent) {
            $this->otpService->storePendingValue($cv, 'email', OtpContext::VerifyEmail, $pendingEmail);
        }

        return $this->respondToSend($cv, 'email', OtpContext::VerifyEmail, $status);
    }

    public function verifyMobileOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $status = $this->otpService->attemptVerification($cv, 'mobile', OtpContext::VerifyMobile, $request->validated('otp'));

        if ($response = $this->respondToVerification($cv, 'mobile', OtpContext::VerifyMobile, $status)) {
            return $response;
        }

        $pendingMobile = $this->otpService->pullPendingValue($cv, 'mobile', OtpContext::VerifyMobile);

        if ($pendingMobile !== null && $pendingMobile !== $cv->mobile) {
            $cv->update(['mobile' => $pendingMobile]);
        }

        return $this->otpVerifiedResponse($cv, 'mobile');
    }

    public function verifyEmailOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $status = $this->otpService->attemptVerification($cv, 'email', OtpContext::VerifyEmail, $request->validated('otp'));

        if ($response = $this->respondToVerification($cv, 'email', OtpContext::VerifyEmail, $status)) {
            return $response;
        }

        $pendingEmail = $this->otpService->pullPendingValue($cv, 'email', OtpContext::VerifyEmail);

        if ($pendingEmail !== null && $pendingEmail !== $cv->email) {
            $cv->update(['email' => $pendingEmail]);
        }

        return $this->otpVerifiedResponse($cv, 'email');
    }

    public function submit(Request $request, string $uuid): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        if (! $cv->isDraft()) {
            return response()->json([
                'message' => __('cv.only_draft_submittable'),
            ], 422);
        }

        if (! $cv->isFullyVerified()) {
            return response()->json([
                'message' => __('cv.not_verified'),
            ], 422);
        }

        $cv = $this->cvService->submit($cv);

        return response()->json([
            'data' => new CvResource($cv),
            'message' => __('cv.submitted'),
        ]);
    }

    public function review(Request $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        if (! $cv->isSubmitted()) {
            return response()->json([
                'message' => __('cv.only_submitted_reviewable'),
            ], 422);
        }

        $cv = $this->cvService->review($cv, $request->user()?->getKey());

        return response()->json([
            'data' => new CvResource($cv),
            'message' => __('cv.reviewed'),
        ]);
    }

    public function reject(RejectCvRequest $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        if (! $cv->isSubmitted()) {
            return response()->json([
                'message' => __('cv.only_submitted_rejectable'),
            ], 422);
        }

        $cv = $this->cvService->reject($cv, $request->validated('reason'), $request->user()?->getKey());

        return response()->json([
            'data' => new CvResource($cv),
            'message' => __('cv.rejected'),
        ]);
    }

    protected function otpLang(string $key, array $replace = []): string
    {
        return __("cv.{$key}", $replace);
    }
}
