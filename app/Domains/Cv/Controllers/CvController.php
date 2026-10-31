<?php

namespace App\Domains\Cv\Controllers;

use App\Contracts\Authorization;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Requests\RejectCvRequest;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
use App\Http\Concerns\InitiatesCandidate;
use App\Http\Requests\Candidate\SubmitSectionRequest;
use App\Http\Responses\OtpResponder;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
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
