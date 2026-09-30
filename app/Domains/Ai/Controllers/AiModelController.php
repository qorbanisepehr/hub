<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Resources\AiModelResource;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The flat, cross-provider read of the model registry.
 *
 * A separate controller from the provider one even though it returns the same
 * resource, because the two answer different questions. Settings asks "what
 * does this provider offer" and edits a row through its parent — a model has
 * no address of its own, which is why every write below is nested under
 * `/ai/providers/{provider}`. A consumer outside Settings asks the other
 * question: "which models can I pick for this one run", which spans providers
 * and has nothing to do with editing. Folding it into the provider controller
 * would put a cross-provider read behind a name that says otherwise.
 *
 * The read carries `ai.provider.view` for the same reason the registry does:
 * picking a model is choosing among the credentials somebody else configured,
 * and who may know which credentials exist is exactly what that permission
 * already answers. It is not a new exposure — it is a narrower shape of the
 * same one, deliberately without `base_url`, `has_api_key` or `headers`, which
 * a dropdown has no use for.
 */
class AiModelController
{
    private const SEARCHABLE = ['model_name', 'label'];

    /**
     * Every model a run may be sent to, across all active providers.
     *
     * Only pickable rows: a deactivated model, or one behind a deactivated
     * provider, is not offered. That is the resolver's own rule
     * ({@see AiProviderResolver::activeModel()}) and
     * the list has to match it exactly — a picker that offers a row the
     * resolver will refuse is a control that lies about what it does.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($providers) => $providers->where('is_active', true));

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $query->orderBy(ListQuery::sort($request, ['model_name', 'id'], 'model_name'), ListQuery::order($request, 'asc'));

        // Id last, as a tiebreaker only: two providers can hold the same model
        // name, and a page whose order shifted between requests would make the
        // list look like it had lost a row.
        $models = $query
            ->orderBy('id')
            ->paginate(ListQuery::perPage($request, 50, 200))
            ->withQueryString();

        return AiModelResource::collection($models);
    }
}
