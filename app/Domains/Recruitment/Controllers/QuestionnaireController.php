<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\InitQuestionnaireRequest;
use App\Domains\Recruitment\Requests\SaveQuestionnaireRequest;
use App\Domains\Recruitment\Requests\SubmitQuestionnaireRequest;
use App\Domains\Recruitment\Requests\VerifyQuestionnaireRequest;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class QuestionnaireController extends Controller
{
    public function init(InitQuestionnaireRequest $request): JsonResponse
    {
        $data = $request->validated();

        $questionnaire = Questionnaire::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'status' => 'draft',
            'mobile_otp' => Questionnaire::generateOtp(),
            'email_otp' => Questionnaire::generateOtp(),
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('recruitment.questionnaire.created'),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    public function save(SaveQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $data = $request->validated();

        // Reset verification if email changed
        if (isset($data['email']) && $data['email'] !== $questionnaire->email) {
            $data['email_verified_at'] = null;
            $data['email_otp'] = Questionnaire::generateOtp();
        }

        // Reset verification if mobile changed
        if (isset($data['mobile']) && $data['mobile'] !== $questionnaire->mobile) {
            $data['mobile_verified_at'] = null;
            $data['mobile_otp'] = Questionnaire::generateOtp();
        }

        $questionnaire->update($data);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.saved'),
        ]);
    }

    public function sendMobileOtp(string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $questionnaire->update([
            'mobile_otp' => Questionnaire::generateOtp(),
        ]);

        return response()->json([
            'message' => __('recruitment.questionnaire.otp_sent'),
        ]);
    }

    public function sendEmailOtp(string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $questionnaire->update([
            'email_otp' => Questionnaire::generateOtp(),
        ]);

        return response()->json([
            'message' => __('recruitment.questionnaire.otp_sent'),
        ]);
    }

    public function verifyMobileOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        if ($request->validated('otp') !== $questionnaire->mobile_otp) {
            return response()->json([
                'message' => __('recruitment.questionnaire.otp_invalid'),
            ], 422);
        }

        $questionnaire->update([
            'mobile_verified_at' => now(),
            'mobile_otp' => null,
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.verified'),
        ]);
    }

    public function verifyEmailOtp(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        if ($request->validated('otp') !== $questionnaire->email_otp) {
            return response()->json([
                'message' => __('recruitment.questionnaire.otp_invalid'),
            ], 422);
        }

        $questionnaire->update([
            'email_verified_at' => now(),
            'email_otp' => null,
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.verified'),
        ]);
    }

    public function submit(SubmitQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        if (! $questionnaire->isMobileVerified()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.mobile_not_verified'),
            ], 422);
        }

        if (! $questionnaire->isEmailVerified()) {
            return response()->json([
                'message' => __('recruitment.questionnaire.email_not_verified'),
            ], 422);
        }

        $questionnaire->update([
            'status' => 'submitted',
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => __('recruitment.questionnaire.submitted'),
        ]);
    }
}
