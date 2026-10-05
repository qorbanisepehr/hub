<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Requests\UpdateAiAgentRequest;
use App\Domains\Ai\Resources\AiAgentResource;
use App\Domains\Ai\Services\AiProviderService;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The agent registry surface. Read is `ai.provider.view`; the only write is
 * the binding, which is `ai.provider.manage`.
 *
 * There is no create or delete here on purpose. An agent is a class in the
 * domain that owns it (D28), so the registry records which of those classes the
 * hub runs and where its binding points — it does not define agents. A binding
 * whose `agent_class` no longer exists would point at nothing, and the runner
 * would only discover that on the next request for that key.
 *
 * Read through the store's SQL page rather than in memory, like the provider
 * list: a binding is a row like any other, and its searchable fields live in
 * the jsonb where `->>` can reach them on either database. `created_at` is not
 * sortable, for the same reason it is not sortable there — a binding has no
 * timestamp of its own.
 *
 * @return AnonymousResourceCollection<int, AiAgentResource>
 */
class AiAgentController
{
    /** @var array<string, string> client-facing name => document field */
    private const SORTABLE = ['key' => 'key', 'label' => 'label', 'agent_class' => 'agent_class'];

    private const SEARCHABLE = ['key', 'label', 'agent_class', 'provider'];

    public function __construct(
        private readonly AiProviderService $providers,
        private readonly SystemSettingsRepositoryInterface $store,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $page = $this->store->page(
            setting: AiAgent::class,
            term: ListQuery::filter($request),
            searchable: self::SEARCHABLE,
            sort: self::SORTABLE[ListQuery::sort($request, array_keys(self::SORTABLE), 'key')],
            order: ListQuery::order($request, 'asc'),
            page: max(1, $request->integer('page', 1)),
            perPage: ListQuery::perPage($request, 50, 200),
        );

        // `->map()` rather than `array_map`: the paginator's `setCollection()`
        // is typed to a Collection, and handing it an array is a TypeError
        // that only shows up on the first request through this endpoint.
        $page->setCollection($page->getCollection()->map(
            static fn (AiAgent $agent): AiAgentResource => new AiAgentResource($agent),
        ));

        return AiAgentResource::collection($page);
    }

    public function update(UpdateAiAgentRequest $request, string $agent): AiAgentResource
    {
        $updated = $this->providers->bindAgent(
            $this->providers->agent($agent),
            $request->input('provider'),
            $request->input('model'),
            $this->actorId($request),
        );

        return new AiAgentResource($updated);
    }

    private function actorId(Request $request): ?int
    {
        $user = $request->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
