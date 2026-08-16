<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
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

        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $questionnaires = $query->paginate($perPage);

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
