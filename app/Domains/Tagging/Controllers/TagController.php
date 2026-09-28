<?php

namespace App\Domains\Tagging\Controllers;

use App\Contracts\Authorization;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Requests\TagRequest;
use App\Domains\Tagging\Resources\TagResource;
use App\Domains\Tagging\Services\TagService;
use App\Domains\Tagging\TaggableType;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tag management (Phase 4, K3 = general service). CRUD is `tag.manage`;
 * the list is `tag.view` so filter bars everywhere can read tag options.
 * Deleting a tag cascades its pivot rows (FK on the taggables migration).
 */
class TagController
{
    private const SEARCHABLE = ['name', 'slug'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tag::query()->withCount('taggables');

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $query->orderBy(ListQuery::sort($request, ['name', 'usage_count'], 'usage_count'), ListQuery::order($request, 'desc'));

        $tags = $query->paginate(ListQuery::perPage($request, 50, 200));

        return TagResource::collection($tags);
    }

    /** Options shape for filter bars / pickers (no pagination). */
    public function options(Request $request): AnonymousResourceCollection
    {
        $query = Tag::query()->orderBy('name');

        if (($search = ListQuery::filter($request)) !== null) {
            $query->where('name', 'like', "%{$search}%");
        }

        return TagResource::collection($query->limit(100)->get());
    }

    public function store(TagRequest $request): TagResource
    {
        $tag = Tag::create([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
        ]);

        return new TagResource($tag);
    }

    public function update(TagRequest $request, Tag $tag): TagResource
    {
        $tag->update([
            'name' => $request->string('name')->toString(),
        ]);

        return new TagResource($tag->refresh());
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(['message' => __('messages.deleted_successfully')]);
    }

    /**
     * Attach/detach tags on an entity (sync semantics via TagService).
     * Gated twice: `tag.manage` (route middleware) plus the taggable's own
     * update permission — retagging is a write on the tagged entity (P5).
     */
    public function attach(Authorization $authorization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'taggable_type' => ['required', 'string', 'in:board-meeting'],
            'taggable_id' => ['required', 'integer', 'min:1'],
            'slugs' => ['required', 'array'],
            'slugs.*' => ['required', 'string', 'max:100'],
        ]);

        $model = $this->resolveTaggable($validated['taggable_type'], (int) $validated['taggable_id']);

        if (($permission = TaggableType::updatePermissionFor($validated['taggable_type'])) !== null) {
            $actor = $request->user();

            if ($actor === null) {
                abort(401);
            }

            $authorization->authorize($actor, $permission, $model);
        }

        app(TagService::class)->syncTags($model, $validated['slugs']);

        return response()->json([
            'tags' => $model->tags->map(fn (Tag $t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
        ]);
    }

    private function resolveTaggable(string $routeType, int $id): Model
    {
        $class = TaggableType::classFor($routeType)
            ?? throw new \InvalidArgumentException("Unknown taggable type [$routeType].");

        return $class::query()->findOrFail($id);
    }
}
