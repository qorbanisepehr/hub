<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Resources\QuestionnaireResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class RecruitmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Questionnaire::query()->where('status', 'submitted');

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

    public function show(string $questionnaire): JsonResponse
    {
        $model = Questionnaire::where('uuid', $questionnaire)
            ->orWhere('id', $questionnaire)
            ->firstOrFail();

        return response()->json([
            'data' => new QuestionnaireResource($model),
        ]);
    }
}
