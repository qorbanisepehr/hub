<?php

namespace App\Domains\Tagging\Services;

use App\Domains\Tagging\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The general tagging contract (K3): every domain tags its models through
 * this service instead of touching the pivot directly — one place owns
 * slug resolution, upsert semantics, and AND-filtering.
 *
 * Tags are matched by slug (stable, URL-safe) and created on first use,
 * so importers and the UI never need tag IDs.
 */
readonly class TagService
{
    /**
     * Attach `$slugs` to `$model`, creating missing tags, and detach any
     * currently-attached tag not in the list (full sync semantics).
     *
     * @param  list<string>  $slugs
     */
    public function syncTags(Model $model, array $slugs): void
    {
        $slugs = $this->normalize($slugs);

        DB::transaction(function () use ($model, $slugs): void {
            $tagIds = [];

            foreach ($slugs as $slug) {
                $tagIds[] = Tag::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug],
                )->id;
            }

            $model->tags()->sync($tagIds);
        });
    }

    /**
     * Resolve slugs to Tag models; unknown slugs are created. Used by the
     * filter endpoints and the importers (K6 archive carries raw slugs).
     *
     * @param  list<string>  $slugs
     * @return list<Tag>
     */
    public function resolve(array $slugs): array
    {
        $slugs = $this->normalize($slugs);

        return collect($slugs)
            ->map(fn (string $slug) => Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug],
            ))
            ->all();
    }

    /**
     * AND-filter: only models carrying ALL of the given tags (TCK parity,
     * A16). Returns a query the caller can further constrain.
     *
     * @param  list<string>  $slugs
     */
    public function filterQuery(Model $model, array $slugs): Builder
    {
        $slugs = $this->normalize($slugs);

        $query = $model->newQuery();

        if ($slugs === []) {
            return $query;
        }

        foreach ($slugs as $slug) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $slug));
        }

        return $query;
    }

    /**
     * Trim, lowercase, dedupe, drop empties. Persian/Arabic letters are
     * preserved — slugs stay human-readable Persian by convention.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function normalize(array $slugs): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn (string $slug) => mb_strtolower(trim($slug)),
                $slugs,
            ),
            fn (string $slug) => $slug !== '',
        )));
    }
}
