<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Http\Concerns\InitiatesCandidate;
use App\Http\Requests\Candidate\SubmitSectionRequest;
use App\Http\Responses\OtpResponder;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
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
