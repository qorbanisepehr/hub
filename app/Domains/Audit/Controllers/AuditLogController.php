<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Resources\AuditLogDetailResource;
use App\Domains\Audit\Resources\AuditLogResource;
use App\Domains\Audit\Services\AuditQueryService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuditLogController extends ApiController
{
    protected ?string $model = null;

    public function __construct(
        private AuditQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->validateDateFilters($request);

        $filters = $request->only([
            'event', 'category', 'actor_type', 'actor_id', 'actor_role_id',
            'subject_type', 'subject_id', 'date_from', 'date_to',
            'request_id', 'search',
        ]);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $logs = $this->queryService->paginate($filters, $perPage);

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogDetailResource
    {
        return new AuditLogDetailResource($auditLog);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event', 'category', 'actor_type', 'actor_id', 'actor_role_id',
            'subject_type', 'subject_id', 'date_from', 'date_to',
        ]);

        return response()->json([
            'data' => $this->queryService->stats($filters),
        ]);
    }

    /**
     * List distinct events, optionally filtered by category.
     */
    public function events(Request $request): JsonResponse
    {
        $query = AuditLog::query()->select('event')->distinct();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $events = $query->orderBy('event')->pluck('event')->all();

        return response()->json(['data' => $events]);
    }

    /**
     * Validate that date_from and date_to are parseable dates.
     *
     * @throws ValidationException
     */
    private function validateDateFilters(Request $request): void
    {
        foreach (['date_from', 'date_to'] as $param) {
            $value = $request->input($param);

            if ($value === null) {
                continue;
            }

            if (Carbon::parse($value) === false) {
                throw ValidationException::withMessages([
                    $param => "The {$param} must be a valid date.",
                ]);
            }
        }
    }
}
