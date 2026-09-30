<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Requests\UpdateAiAgentRequest;
use App\Domains\Ai\Resources\AiAgentResource;
use App\Domains\Ai\Services\AiProviderService;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The agent registry surface. Read is `ai.provider.view`; the only write is
 * the binding, which is `ai.provider.manage`.
 *
 * There is no create or delete here on purpose. An agent is a class in the
 * domain that owns it (D28), so the registry records which of those classes
 * the hub runs and where its binding points — it does not define agents. A row
 * whose `agent_class` no longer exists would be a binding to nothing, and the
 * runner would only discover that on the next request for that key.
 */
class AiAgentController
{
    private const SEARCHABLE = ['key', 'label', 'agent_class'];

    public function __construct(
        private readonly AiProviderService $providers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AiAgent::query()->with(['provider', 'model']);

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $query->orderBy(ListQuery::sort($request, ['key', 'label', 'created_at'], 'key'), ListQuery::order($request, 'asc'));

        $agents = $query
            ->paginate(ListQuery::perPage($request, 50, 200))
            ->withQueryString();

        return AiAgentResource::collection($agents);
    }

    public function update(UpdateAiAgentRequest $request, AiAgent $agent): AiAgentResource
    {
        $agent = $this->providers->bindAgent($agent, $request->validated());

        return new AiAgentResource($agent->load(['provider', 'model']));
    }
}
