<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\InitQuestionnaireRequest;
use App\Domains\Recruitment\Requests\SaveQuestionnaireRequest;
use App\Domains\Recruitment\Requests\VerifyQuestionnaireRequest;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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
            'current_step' => 0,
            'mobile_otp' => Questionnaire::generateOtp(),
            'email_otp' => Questionnaire::generateOtp(),
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => 'پرسشنامه با موفقیت ایجاد شد.',
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

        $questionnaire->update($data);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'پرسشنامه ذخیره شد.',
        ]);
    }

    public function sendOtp(string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $questionnaire->update([
            'mobile_otp' => Questionnaire::generateOtp(),
            'email_otp' => Questionnaire::generateOtp(),
        ]);

        return response()->json([
            'message' => 'کد تأیید ارسال شد.',
        ]);
    }

    public function verify(VerifyQuestionnaireRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $data = $request->validated();

        $verified = true;

        if ($data['mobile_otp'] !== $questionnaire->mobile_otp) {
            $verified = false;
        }

        if ($data['email_otp'] !== $questionnaire->email_otp) {
            $verified = false;
        }

        if (! $verified) {
            return response()->json([
                'message' => 'کد تأیید نامعتبر است.',
            ], 422);
        }

        DB::transaction(function () use ($questionnaire): void {
            $questionnaire->update([
                'status' => 'submitted',
                'mobile_verified_at' => now(),
                'email_verified_at' => now(),
                'mobile_otp' => null,
                'email_otp' => null,
            ]);
        });

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'پرسشنامه با موفقیت ثبت شد.',
        ]);
    }
}
