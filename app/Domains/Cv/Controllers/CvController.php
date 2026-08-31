<?php

namespace App\Domains\Cv\Controllers;

use App\Contracts\Authorization;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Requests\RejectCvRequest;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
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

class CvController extends Controller
{
    use InitiatesCandidate;
    use OtpResponder;

    public function __construct(
        private Authorization $authorization,
        private CvService $cvService,
        private OtpService $otpService,
        private SessionGrantStore $sessionGrants,
    ) {}

    protected function candidateType(): string
    {
        return 'cv';
    }

    protected function candidateModel(): string
    {
        return Cv::class;
    }

    protected function candidateService(): object
    {
        return $this->cvService;
    }

    protected function candidateResource(mixed $record): mixed
    {
        return new CvResource($record);
    }

    protected function candidateIsFinalized(mixed $record): bool
    {
        return $record instanceof Cv && $record->isApproved();
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

    public function saveSection(string $uuid, string $section, SubmitSectionRequest $request): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        if (! $cv->isEditable()) {
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
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
        ]);

        $pendingMobile = isset($data['mobile']) ? MobileNumber::normalize($data['mobile']) : null;

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

    public function verifyMobileOtp(VerifyOtpCodeRequest $request, string $uuid): JsonResponse
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

    public function verifyEmailOtp(VerifyOtpCodeRequest $request, string $uuid): JsonResponse
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

        if (! $cv->isEditable()) {
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

    public function approve(Request $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $this->authorization->authorize($request->user(), 'cv.approve', $cv);

        if (! $cv->isSubmitted()) {
            return response()->json([
                'message' => __('cv.only_submitted_approvable'),
            ], 422);
        }

        $cv = $this->cvService->approve($cv, $request->user()?->getKey());

        return response()->json([
            'data' => new CvResource($cv),
            'message' => __('cv.approved'),
        ]);
    }

    public function reject(RejectCvRequest $request, string $uuid): JsonResponse
    {
        $cv = $this->cvService->findByUuidOrFail($uuid);

        $this->authorization->authorize($request->user(), 'cv.reject', $cv);

        if (! $cv->isSubmitted() && ! $cv->isApproved()) {
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
