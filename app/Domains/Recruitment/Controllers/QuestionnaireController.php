<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\InitQuestionnaireRequest;
use App\Domains\Recruitment\Requests\SectionSaveRequest;
use App\Domains\Recruitment\Requests\VerifyQuestionnaireRequest;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use App\Domains\Recruitment\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class QuestionnaireController extends Controller
{
    public function __construct(
        private QuestionnaireService $service,
    ) {}

    public function init(InitQuestionnaireRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Check if mobile already exists (any status)
        $existing = Questionnaire::where('mobile', $data['mobile'])->first();

        if ($existing) {
            // Generate OTP for the existing questionnaire
            $otp = Questionnaire::generateOtp();
            $this->service->updateOtp($existing, 'mobile', $otp);

            return response()->json([
                'data' => [
                    'uuid' => $existing->uuid,
                    'status' => $existing->status,
                ],
                'message' => 'این شماره موبایل قبلاً ثبت شده است. کد تأیید ارسال شد.',
                'requires_otp' => true,
            ], 409);
        }

        $questionnaire = $this->service->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.created'),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    /**
     * Save a single section: PUT /questionnaire/{uuid}/sections/{section}
     */
    public function saveSection(string $uuid, string $section, SectionSaveRequest $request): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => 'Only draft questionnaires can be edited.',
            ], 422);
        }

        $data = $request->validated();

        // Handle OTP reset for email/mobile changes
        if ($section === 'contact_info') {
            $this->handleContactOtpReset($questionnaire, $data);
        }

        $questionnaire = $this->service->saveSection($questionnaire, $section, $data);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.saved'),
        ]);
    }

    public function sendMobileOtp(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        $otp = Questionnaire::generateOtp();
        $this->service->updateOtp($questionnaire, 'mobile', $otp);

        return response()->json([
            'message' => __('recruitment.questionnaire.otp_sent'),
        ]);
    }

    public function sendEmailOtp(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        $otp = Questionnaire::generateOtp();
        $this->service->updateOtp($questionnaire, 'email', $otp);

        return response()->json([
            'message' => __('recruitment.questionnaire.otp_sent'),
        ]);
    }

    public function verifyMobileOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if ($request->validated('otp') !== $questionnaire->mobile_otp) {
            return response()->json([
                'message' => __('recruitment.questionnaire.otp_invalid'),
            ], 422);
        }

        $this->service->verifyOtp($questionnaire, 'mobile');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.verified'),
        ]);
    }

    public function verifyEmailOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if ($request->validated('otp') !== $questionnaire->email_otp) {
            return response()->json([
                'message' => __('recruitment.questionnaire.otp_invalid'),
            ], 422);
        }

        $this->service->verifyOtp($questionnaire, 'email');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.verified'),
        ]);
    }

    public function submit(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => 'Only draft questionnaires can be submitted.',
            ], 422);
        }

        if (! $questionnaire->isFullyVerified()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.not_verified'),
            ], 422);
        }

        $questionnaire = $this->service->submit($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.submitted'),
        ]);
    }

    /**
     * POST /questionnaire/{uuid}/review
     */
    public function review(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => 'Only submitted questionnaires can be reviewed.',
            ], 422);
        }

        $questionnaire = $this->service->updateStatus($questionnaire, 'reviewed');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => 'Questionnaire reviewed successfully.',
        ]);
    }

    /**
     * POST /questionnaire/{uuid}/reject
     */
    public function reject(string $uuid): JsonResponse
    {
        $questionnaire = $this->service->findByUuidOrFail($uuid);

        if (! $questionnaire->isSubmitted()) {
            return response()->json([
                'message' => 'Only submitted questionnaires can be rejected.',
            ], 422);
        }

        $questionnaire = $this->service->updateStatus($questionnaire, 'draft');

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => 'Questionnaire sent back to draft.',
        ]);
    }

    private function handleContactOtpReset(Questionnaire $questionnaire, array &$data): void
    {
        if (isset($data['email']) && $data['email'] !== $questionnaire->email) {
            $questionnaire->update([
                'email_verified_at' => null,
                'email_otp' => Questionnaire::generateOtp(),
            ]);
        }

        if (isset($data['mobile']) && $data['mobile'] !== $questionnaire->mobile) {
            $questionnaire->update([
                'mobile_verified_at' => null,
                'mobile_otp' => Questionnaire::generateOtp(),
            ]);
        }
    }
}
