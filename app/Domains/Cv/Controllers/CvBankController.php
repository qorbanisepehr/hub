<?php

namespace App\Domains\Cv\Controllers;

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CvBankController extends Controller
{
    public function __construct(
        private CvService $cvService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Cv::query()->where('status', 'submitted');

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $query->where(function ($q) use ($filter) {
                $q->where('first_name', 'like', "%{$filter}%")
                    ->orWhere('last_name', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%")
                    ->orWhere('mobile', 'like', "%{$filter}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortField = in_array($request->input('sort'), ['created_at', 'updated_at', 'version', 'first_name', 'last_name'])
            ? $request->input('sort')
            : 'created_at';
        $sortDirection = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        return CvResource::collection($query->paginate($perPage));
    }

    public function show(string $cv): JsonResponse
    {
        $model = Cv::where('uuid', $cv)
            ->orWhere('id', $cv)
            ->firstOrFail();

        return response()->json([
            'data' => new CvResource($model),
        ]);
    }

    public function createQuestionnaire(Request $request, string $cv): JsonResponse
    {
        $model = Cv::where('uuid', $cv)->firstOrFail();

        $questionnaire = $this->cvService->createQuestionnaireFromCv($model);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('cv.questionnaire_created'),
        ], 201);
    }
}
