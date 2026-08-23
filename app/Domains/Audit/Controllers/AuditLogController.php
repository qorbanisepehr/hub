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
            'request_id', 'trace_id', 'ip', 'search',
        ]);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        // Presence of the param opts into keyset pagination; `?cursor=` alone
        // fetches the first cursor page.
        $cursor = $request->has('cursor') ? (string) $request->input('cursor', '') : null;
        $sort = $request->input('sort');

        $logs = $this->queryService->paginate(
            $filters,
            $perPage,
            $cursor,
            is_string($sort) ? $sort : null,
        );

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogDetailResource
    {
        $auditLog->load(['actorUser:id,name,avatar_url', 'actorUser.employee:id,user_id,first_name,last_name']);

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
        $events = $this->queryService->distinctEvents(
            category: $request->input('category'),
        );

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

            try {
                Carbon::parse($value);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages([
                    $param => "The {$param} must be a valid date.",
                ]);
            }
        }
    }
}
