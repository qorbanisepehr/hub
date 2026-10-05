<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Resources\AiModelResource;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\Ai\Services\AiProviderService;
use App\Domains\Ai\Value\PickableModel;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The flat, cross-provider read of the model registry.
 *
 * A separate controller from the provider one even though it returns the same
 * resource, because the two answer different questions. Settings asks "what
 * does this provider offer" and edits a model through its parent — a model has
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
 *
 * This is the one list that cannot be a query, and the reason is not
 * inconvenience. A model is an entry inside its provider's document, so
 * "every pickable model across every provider" has no row to select: it is a
 * projection assembled by walking documents. The other two AI lists are rows
 * and stay in SQL, which is why the paging below is private to this class
 * rather than a shared helper — one caller is not a reason to generalize.
 */
class AiModelController
{
    public function __construct(
        private readonly AiProviderService $providers,
    ) {}

    /**
    public function __construct(
        private readonly AiProviderService $providers,
    ) {}

    /**
     * Every model a run may be sent to, across all active providers.
     *
     * Only pickable models: a deactivated one, or one behind a deactivated
     * provider, is not offered. That is the resolver's own rule
     * ({@see AiProviderResolver::canServe()}) and the
     * list has to match it exactly — a picker that offers a model the resolver
     * will refuse is a control that lies about what it does.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = $this->search($this->pickable(), $request);
        $items = $this->sort(
            $items,
            ListQuery::sort($request, ['model_name', 'label', 'display_label', 'provider_name'], 'model_name'),
            ListQuery::order($request, 'asc') === 'desc',
        );

        $perPage = ListQuery::perPage($request, 50, 200);
        $page = max(1, $request->integer('page', 1));

        // Built by hand because there is no query builder underneath, but shaped
        // exactly like one: the client's tables read `meta` and `links` off
        // every list, and a collection with neither would render as a page
        // number of one no matter how many models there are.
        $page = new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // `->map()` rather than `array_map`, because the paginator's
        // `setCollection()` is typed to a Collection and an array is a
        // TypeError. Same reason as the other two lists, which get it for free
        // because `page()` hands back a paginator already.
        $page->setCollection($page->getCollection()->map(
            static fn (PickableModel $pickable): AiModelResource => new AiModelResource(
                $pickable->model,
                $pickable->providerName,
            ),
        ));

        return AiModelResource::collection($page);
    }

    /**
     * @return list<PickableModel>
     */
    private function pickable(): array
    {
        $pickable = [];

        foreach ($this->providers->providers() as $provider) {
            if (! $provider->isActive()) {
                continue;
            }

            foreach ($provider->models() as $model) {
                if ($model->isActive) {
                    $pickable[] = new PickableModel($model, $provider->name());
                }
            }
        }

        return $pickable;
    }

    /**
     * Free-text over the fields a client can see on this list.
     *
     * Case-insensitive, which the SQL lists got from `->>` and a `lower()`
     * comparison. `%` and `_` are literal, because a filter typed as `llama_3`
     * matching `llamaX3` is a filter that returns more than was asked for.
     *
     * @param  list<PickableModel>  $items
     * @return list<PickableModel>
     */
    private function search(array $items, Request $request): array
    {
        $term = ListQuery::filter($request);

        if ($term === null) {
            return $items;
        }

        return array_values(array_filter($items, static function (PickableModel $pickable) use ($term): bool {
            $haystacks = [
                $pickable->model->name,
                $pickable->model->label,
                $pickable->model->displayLabel(),
                $pickable->providerName,
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== null && Str::contains(mb_strtolower($haystack), mb_strtolower($term))) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<PickableModel>  $items
     * @return list<PickableModel>
     */
    private function sort(array $items, ?string $sort, bool $descending): array
    {
        $field = fn (PickableModel $pickable): ?string => match ($sort) {
            'label' => $pickable->model->label,
            'display_label' => $pickable->model->displayLabel(),
            'provider_name' => $pickable->providerName,
            default => $pickable->model->name,
        };

        // The provider stays the tiebreaker whichever way the sort runs, so the
        // order is total: two providers can serve the same model name, and
        // reversing the sort has to reverse the list exactly rather than
        // reshuffle the pairs that tie.
        usort($items, static function (PickableModel $a, PickableModel $b) use ($field, $descending): int {
            $compared = ($field($a) ?? '') <=> ($field($b) ?? '');

            if ($compared === 0) {
                return $a->providerName <=> $b->providerName;
            }

            return $descending ? -$compared : $compared;
        });

        return $items;
    }
}
