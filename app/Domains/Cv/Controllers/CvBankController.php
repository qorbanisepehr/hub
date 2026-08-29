<?php

namespace App\Domains\Cv\Controllers;

use App\Contracts\Authorization;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class CvBankController extends Controller
{
    public function __construct(
        private Authorization $authorization,
        private CvService $cvService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Cv::query()
            ->with('documentUsages.document')
            ->with('reviewer.employee');

        $this->authorization->scope($request->user(), 'cv.view', $query);

        if ($filter = ListQuery::filter($request)) {
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

        $sortField = ListQuery::sort($request, ['created_at', 'updated_at', 'version', 'first_name', 'last_name'], 'created_at');
        $sortDirection = ListQuery::order($request);
        $query->orderBy($sortField, $sortDirection);

        return CvResource::collection($query->paginate(ListQuery::perPage($request)));
    }

    public function show(Request $request, string $cv): JsonResponse
    {
        // A UUID literal can't be compared against the uuid column by Postgres
        // when the route receives the numeric id, so branch on the value type.
        $model = Str::isUuid($cv)
            ? Cv::with('questionnaire')->with('documentUsages.document')->with('reviewer.employee')->where('uuid', $cv)->firstOrFail()
            : Cv::with('questionnaire')->with('documentUsages.document')->with('reviewer.employee')->where('id', $cv)->firstOrFail();

        $this->authorization->authorize($request->user(), 'cv.view', $model);

        return response()->json([
            'data' => new CvResource($model),
        ]);
    }

    public function createQuestionnaire(Request $request, string $cv): JsonResponse
    {
        $model = Cv::where('uuid', $cv)->firstOrFail();

        $this->authorization->authorize($request->user(), 'cv.create-questionnaire', $model);

        $questionnaire = $this->cvService->createQuestionnaireFromCv(
            $model,
            $request->user()?->getKey(),
        );

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('cv.questionnaire_created'),
        ], 201);
    }
}
