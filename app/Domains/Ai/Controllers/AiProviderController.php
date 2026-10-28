<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Requests\AiModelRequest;
use App\Domains\Ai\Requests\StoreAiProviderRequest;
use App\Domains\Ai\Requests\UpdateAiProviderRequest;
use App\Domains\Ai\Resources\AiModelResource;
use App\Domains\Ai\Resources\AiProviderResource;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Domains\Ai\Services\AiProviderService;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The provider and model registry surface (`ai.provider.view` to read,
 * `ai.provider.manage` to write, per R4).
 *
 * The permission lives on the route rather than in a policy because there is no
 * per-row question to answer: a provider is not owned by anyone, so every reader
 * of this table is equally allowed to read all of it.
 *
 * The controller builds value objects and hands them to the service; it never
 * assembles a document itself and never writes. That split is what keeps "a
 * provider is written as one aggregate" true no matter which endpoint did the
 * writing — the alternative is each of the four write paths deciding for itself
 * which fields belong together, which is exactly how a document ends up with a
 * half-updated model list.
 *
 * Every route parameter is a name. There is no route-model binding for these,
 * because there is no model to bind: `AiProvider` is a value object rebuilt
 * from its stored document, and binding it would mean resolving it before the
 * permission and validation had run.
 */
class AiProviderController
{
    /**
     * Sortable columns, and the ones a filter box matches against.
     *
     * `created_at` is not here and that is not an oversight: a document has no
     * timestamp of its own, and borrowing the row's would mean sorting
     * configuration by when a file happened to be rewritten. The client's
     * tables fall back to the default order for a column the endpoint does not
     * have, so nothing breaks.
     *
     * @return array<string, string> client-facing name => document field
     */
    private const SORTABLE = ['name' => 'key', 'driver' => 'driver'];

    private const SEARCHABLE = ['name' => 'key', 'driver' => 'driver', 'base_url' => 'base_url'];

    public function __construct(
        private readonly AiProviderService $providers,
        private readonly SystemSettingsRepositoryInterface $store,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $default = $this->providers->defaultProviderName();

        $page = $this->store->page(
            setting: AiProvider::class,
            term: ListQuery::filter($request),
            searchable: array_values(self::SEARCHABLE),
            sort: self::SORTABLE[ListQuery::sort($request, array_keys(self::SORTABLE), 'name')],
            order: ListQuery::order($request, 'asc'),
            page: max(1, $request->integer('page', 1)),
            perPage: ListQuery::perPage($request, 50, 200),
        );

        // The paginator is kept and its *items* replaced, rather than building a
        // fresh collection from an array. That is what carries `meta` and
        // `links` through: the client's tables read both, and a collection
        // constructed from an array has neither, so the page would arrive
        // looking like a single unpaged page.
        //
        // `->map()` rather than `array_map` because the paginator's
        // `setCollection()` is typed to a Collection — handing it an array is a
        // TypeError, and one that only appears on the first real request.
        $page->setCollection($page->getCollection()->map(
            static fn (AiProvider $provider): AiProviderResource => new AiProviderResource($provider, $default),
        ));

        return AiProviderResource::collection($page);
    }

    public function store(StoreAiProviderRequest $request): JsonResponse
    {
        $provider = AiProvider::make(
            $request->string('name')->toString(),
            AiDriver::from($request->string('driver')->toString()),
        )
            ->withBaseUrl($this->filledOrNull($request, 'base_url'))
            ->withHeaders($this->headers($request))
            ->withApiKey($request->filled('api_key') ? $request->string('api_key')->toString() : null)
            ->asActive($request->boolean('is_active', true));

        return $this->created(new AiProviderResource(
            $this->providers->createProvider($provider, $this->actorId($request)),
        ));
    }

    public function update(UpdateAiProviderRequest $request, string $provider): AiProviderResource
    {
        $current = $this->providers->provider($provider);

        // Each `whenHas` is one "absent means keep" field. The old version of
        // this was an array merge, which could not express the difference
        // between "send null to clear" and "send nothing to keep" — and that
        // difference is the whole reason a credential is never destroyed by an
        // unrelated edit.
        $updated = $current
            ->withBaseUrl($request->has('base_url') ? $this->filledOrNull($request, 'base_url') : $current->baseUrl())
            ->withHeaders($request->has('headers') ? $this->headers($request) : $current->headers())
            ->withApiKey($this->apiKey($request, $current))
            ->asActive($request->has('is_active') ? $request->boolean('is_active') : $current->isActive());

        return new AiProviderResource(
            $this->providers->updateProvider($updated, $this->actorId($request)),
        );
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        $this->providers->deleteProvider($provider, $this->actorId($request));

        return response()->json(['message' => __('messages.deleted_successfully')]);
    }

    /**
     * Promote a provider to be the default. Its own endpoint rather than an
     * update with `is_default`, because the list's action column is the only
     * place that offers it and a dedicated verb cannot be reached by a client
     * that sends the whole row back — which would promote a provider as a side
     * effect of editing its URL.
     */
    public function makeDefault(Request $request, string $provider): AiProviderResource
    {
        return new AiProviderResource(
            $this->providers->makeDefault($provider, $this->actorId($request)),
            $provider,
        );
    }

    /**
     * Run one minimal prompt against the stored credential.
     *
     * An optional `?model=` lets an administrator check a model name before
     * saving it — most "the provider is broken" reports are really "this
     * gateway does not serve that model", and finding that out here is cheaper
     * than finding it out on a meeting.
     *
     * 422 rather than 5xx on failure: the cause is almost always something the
     * administrator can fix on the form they are looking at, and the vendor's
     * own message is the useful part. It is reported, not rendered.
     */
    public function test(AiConnectionTester $tester, Request $request, string $provider): JsonResponse
    {
        try {
            $result = $tester->test(
                $this->providers->provider($provider),
                $request->string('model')->toString() ?: null,
            );
        } catch (AiProviderUnreachable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function storeModel(AiModelRequest $request, string $provider): JsonResponse
    {
        $updated = $this->providers->addModel(
            $this->providers->provider($provider),
            $this->model($request),
            $this->actorId($request),
        );

        return $this->created(new AiModelResource(
            $this->model($request),
            $updated->name(),
        ));
    }

    public function updateModel(AiModelRequest $request, string $provider, string $model): AiModelResource
    {
        $wanted = $this->model($request);

        $updated = $this->providers->updateModel(
            $this->providers->provider($provider),
            $model,
            $wanted,
            $this->actorId($request),
        );

        return new AiModelResource($wanted, $updated->name());
    }

    public function destroyModel(Request $request, string $provider, string $model): JsonResponse
    {
        $this->providers->deleteModel(
            $this->providers->provider($provider),
            $model,
            $this->actorId($request),
        );

        return response()->json(['message' => __('messages.deleted_successfully')]);
    }

    /**
     * The validated request as the value object the registry stores.
     *
     * Capabilities come off the wire as strings and go in as enum cases, which
     * is the conversion the old cast did for free. Doing it here rather than
     * letting a string reach the document matters: `toArray()` calls `->value`
     * on each one, so a string would not be stored wrong, it would fail on the
     * way back out.
     */
    private function model(AiModelRequest $request): AiModel
    {
        return new AiModel(
            name: $request->string('model_name')->toString(),
            label: $this->filledOrNull($request, 'label'),
            capabilities: array_map(
                static fn (string $capability): AiCapability => AiCapability::from($capability),
                $request->input('capabilities', []),
            ),
            isActive: $request->boolean('is_active', true),
        );
    }

    /**
     * `null` for an absent *or* empty value, because a text input the browser
     * cleared arrives as `""` and storing that as a base URL would produce a
     * provider that fails every request with an unparseable address.
     */
    private function filledOrNull(Request $request, string $key): ?string
    {
        return $request->filled($key) ? $request->string($key)->toString() : null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        /** @var array<string, string> $headers */
        $headers = $request->input('headers', []);

        return $headers;
    }

    /**
     * Absent keeps the stored credential, a sent one replaces it, and
     * `clear_api_key` removes it. The flag wins over a sent key so a form that
     * submits both cannot resurrect a credential the same request removed.
     */
    private function apiKey(UpdateAiProviderRequest $request, AiProvider $current): ?string
    {
        if ($request->boolean('clear_api_key')) {
            return null;
        }

        if ($request->filled('api_key')) {
            return $request->string('api_key')->toString();
        }

        return $current->apiKey();
    }

    private function actorId(Request $request): ?int
    {
        $user = $request->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }

    /**
     * A resource answered with 201.
     *
     * Said explicitly because it used to be inferred. Laravel's resource
     * response returns 201 only when it wraps an Eloquent model with
     * `wasRecentlyCreated` set, and a value object has no such flag — so the
     * moment the registry stopped being rows, every create silently dropped to
     * 200. Nothing in the application checks that status, but the tests do, and
     * a client that keys off it should not have to know that this one endpoint
     * is special.
     */
    private function created(JsonResource $resource): JsonResponse
    {
        return $resource->response()->setStatusCode(201);
    }
}
