<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Requests\AiModelRequest;
use App\Domains\Ai\Requests\StoreAiProviderRequest;
use App\Domains\Ai\Requests\UpdateAiProviderRequest;
use App\Domains\Ai\Resources\AiModelResource;
use App\Domains\Ai\Resources\AiProviderResource;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Domains\Ai\Services\AiProviderService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The provider and model registry surface (`ai.provider.view` to read,
 * `ai.provider.manage` to write, per R4).
 *
 * The permission lives on the route rather than in a policy because there is
 * no per-row question to answer: a provider is not owned by anyone, so every
 * reader of this table is equally allowed to read all of it. The one
 * resource-level rule that does exist — a model must belong to the provider
 * it is bound to — is enforced in the service, where it is a fact about the
 * registry rather than about this HTTP shape.
 */
class AiProviderController
{
    private const SEARCHABLE = ['name', 'base_url'];

    public function __construct(
        private readonly AiProviderService $providers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AiProvider::query()->with('models');

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $query->orderBy(ListQuery::sort($request, ['name', 'driver', 'created_at'], 'name'), ListQuery::order($request, 'asc'));

        // The default first, then by name: the row everything else inherits
        // from is the one an administrator is looking for first.
        $providers = $query
            ->paginate(ListQuery::perPage($request, 50, 200))
            ->withQueryString();

        return AiProviderResource::collection($providers);
    }

    public function store(StoreAiProviderRequest $request): AiProviderResource
    {
        return new AiProviderResource($this->providers->create($this->attributes($request)));
    }

    public function update(UpdateAiProviderRequest $request, AiProvider $provider): AiProviderResource
    {
        return new AiProviderResource($this->providers->update($provider, $this->attributes($request)));
    }

    public function destroy(AiProvider $provider): JsonResponse
    {
        $this->providers->delete($provider);

        return response()->json(['message' => __('messages.deleted_successfully')]);
    }

    /**
     * Promote a row to be the default. Its own endpoint rather than a PATCH
     * with `is_default`, because the list's action column is the only place
     * that offers it and a dedicated verb cannot be reached by a client that
     * sends the whole row back — which would promote a row as a side effect
     * of editing its URL.
     */
    public function makeDefault(AiProvider $provider): AiProviderResource
    {
        return new AiProviderResource($this->providers->makeDefault($provider));
    }

    /**
     * Run one minimal prompt against the stored credential.
     *
     * An optional `?model=` lets an administrator check a model name before
     * saving it as a row: most "the provider is broken" reports are really
     * "this gateway does not serve that model", and finding that out here is
     * cheaper than finding it out on a meeting.
     *
     * 422 rather than 5xx on failure: the cause is almost always something the
     * administrator can fix on the form they are looking at, and the vendor's
     * own message is the useful part. It is reported, not rendered.
     */
    public function test(AiConnectionTester $tester, Request $request, AiProvider $provider): JsonResponse
    {
        try {
            $result = $tester->test($provider, $request->string('model')->toString() ?: null);
        } catch (AiProviderUnreachable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function storeModel(AiModelRequest $request, AiProvider $provider): AiModelResource
    {
        $model = $this->providers->addModel($provider, [
            'model_name' => $request->string('model_name')->toString(),
            'label' => $request->input('label'),
            'capabilities' => $request->input('capabilities', []),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new AiModelResource($model);
    }

    public function updateModel(AiModelRequest $request, AiProvider $provider, int $ai_model): AiModelResource
    {
        $model = $provider->models()->findOrFail($ai_model);

        $model = $this->providers->updateModel($model, [
            'model_name' => $request->string('model_name')->toString(),
            'label' => $request->input('label'),
            'capabilities' => $request->input('capabilities', []),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new AiModelResource($model);
    }

    public function destroyModel(AiProvider $provider, int $ai_model): JsonResponse
    {
        $this->providers->deleteModel($provider->models()->findOrFail($ai_model));

        return response()->json(['message' => __('messages.deleted_successfully')]);
    }

    /**
     * Only the fields the form owns, so a request can never write `id` or
     * `created_at` through `fillable`.
     *
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        // `name` is in the list but only the *create* request validates it, so
        // an update never carries one through: reading the raw input here
        // would let a rename in, which the name-free update rules exist to
        // prevent (see UpdateAiProviderRequest).
        $attributes = $request->safe()->only(['name', 'driver', 'base_url', 'headers', 'is_active', 'is_default']);

        // Absent means "keep", not "clear" — see UpdateAiProviderRequest. The
        // flag wins over a sent key so a form that submits both cannot
        // resurrect a credential the same request just removed.
        if ($request->boolean('clear_api_key')) {
            $attributes['api_key'] = null;
        } elseif ($request->filled('api_key')) {
            $attributes['api_key'] = $request->string('api_key')->toString();
        }

        return $attributes;
    }
}
