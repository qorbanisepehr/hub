<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class QuestionnaireManagementController extends Controller
{
    public function __construct(
        private Authorization $authorization,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Questionnaire::query()->where('status', 'submitted');

        $this->authorization->scope($request->user(), 'questionnaire.view', $query);

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

        $sortField = ListQuery::sort($request, default: 'created_at');
        $sortDirection = ListQuery::order($request);
        $query->orderBy($sortField, $sortDirection);

        $questionnaires = $query->paginate(ListQuery::perPage($request));

        return QuestionnaireResource::collection($questionnaires);
    }

    public function show(Request $request, string $questionnaire): JsonResponse
    {
        $model = Questionnaire::where('uuid', $questionnaire)
            ->orWhere('id', $questionnaire)
            ->firstOrFail();

        $this->authorization->authorize($request->user(), 'questionnaire.view', $model);

        return response()->json([
            'data' => new QuestionnaireResource($model),
        ]);
    }
}
