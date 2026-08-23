<?php

namespace App\Domains\FormOptions\Controllers;

use App\Contracts\Authorization;
use App\Domains\FormOptions\Models\FormOption;
use App\Domains\FormOptions\Requests\StoreFormOptionRequest;
use App\Domains\FormOptions\Requests\UpdateFormOptionRequest;
use App\Domains\FormOptions\Resources\FormOptionResource;
use App\Domains\FormOptions\Services\FormOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormOptionController
{
    public function __construct(
        private readonly FormOptionService $service,
        private Authorization $authorization,
    ) {}

    /**
     * Public: every selectable group's flat, active options keyed by group
     * name. Hierarchical location groups are excluded; they are served by
     * {@see show()} with the parent_value filter.
     */
    public function index(): JsonResponse
    {
        $groups = array_values(array_diff(
            $this->service->getGroups(),
            FormOptionService::LOCATION_GROUPS,
        ));
        $data = [];

        foreach ($groups as $group) {
            $data[$group] = $this->service->getOptions($group);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Public: one group's flat, active options. Location groups support the
     * optional parent_value query param to fetch a single parent's children,
     * and any group supports the optional search param to filter by label
     * (results are capped by the optional limit, defaulting to 50).
     */
    public function show(Request $request, string $group): JsonResponse
    {
        $search = $request->query('search');
        $limitParam = $request->query('limit');

        $limit = is_numeric($limitParam)
            ? min(max((int) $limitParam, 1), 100)
            : null;

        return response()->json([
            'data' => $this->service->getOptions(
                $group,
                $request->query('parent_value'),
                is_string($search) ? $search : null,
                $limit,
            ),
        ]);
    }

    /**
     * Public: resolve stored values back to their option rows, including
     * inactive ones, so saved records can display the label of an option that
     * was deactivated after the record was written.
     */
    public function resolve(Request $request, string $group): JsonResponse
    {
        // Accept both `?values=a,b,c` and `?values[]=a&values[]=b`.
        $raw = $request->query('values');

        $values = collect(is_array($raw) ? $raw : explode(',', (string) $raw))
            ->filter(fn ($value): bool => is_string($value))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->take(100)
            ->values()
            ->all();

        return response()->json([
            'data' => $this->service->resolveValues($group, $values),
        ]);
    }

    public function store(StoreFormOptionRequest $request): FormOptionResource
    {
        return new FormOptionResource($this->service->create($request->validated()));
    }

    public function update(UpdateFormOptionRequest $request, FormOption $option): FormOptionResource|JsonResponse
    {
        $this->authorization->authorize($request->user(), 'form-options.manage', $option);

        // Value immutability (v6 §49): once an option's value is stored in any
        // form section, renaming it would orphan the persisted data. Only the
        // presentation fields may change afterwards.
        $new = $request->validated('value');

        if (is_string($new) && $new !== $option->value && $this->service->isReferenced($option)) {
            return response()->json([
                'message' => 'Option value is referenced by existing records and cannot be changed. Deactivate it and create a new option instead.',
            ], 409);
        }

        return new FormOptionResource($this->service->update($option, $request->validated()));
    }

    public function destroy(Request $request, FormOption $option): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'form-options.manage', $option);

        if ($this->service->isReferenced($option)) {
            return response()->json([
                'message' => 'Option is referenced by existing records and cannot be deleted. Deactivate it instead.',
            ], 409);
        }

        $this->service->delete($option);

        return response()->json(null, 204);
    }

    public function toggleActive(Request $request, FormOption $option): FormOptionResource
    {
        $this->authorization->authorize($request->user(), 'form-options.manage', $option);

        return new FormOptionResource($this->service->toggleActive($option));
    }

    public function groups(): JsonResponse
    {
        return response()->json(['data' => $this->service->getAdminGroups()]);
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $query = FormOption::query();

        if ($group = $request->query('group')) {
            $query->ofGroup($group);
        } else {
            $query->whereNotIn('group', FormOptionService::LOCATION_GROUPS);
        }

        $this->authorization->scope($request->user(), 'form-options.manage', $query);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return FormOptionResource::collection(
            $query->ordered()->paginate($perPage),
        );
    }
}
