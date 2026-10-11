<?php

namespace App\Domains\Tagging\Services;

use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Requests\TagRequest;
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
        $this->syncTagProposals($model, array_map(
            static fn (string $slug): array => ['name' => $slug, 'slug' => $slug],
            $slugs,
        ));
    }

    /**
     * Attach a run's tag proposals to `$model`, creating the missing ones with
     * the name the run gave them (full sync semantics).
     *
     * A proposal is two strings because the hub keeps two: `name` is the
     * Persian label a person reads, `slug` is the stable key filters and pivots
     * use. The slug-only {@see syncTags} cannot carry a name, so every tag it
     * creates is labelled with its own slug — right for an importer copying an
     * existing vocabulary, wrong for a model that was asked to write the label
     * in Persian.
     *
     * @param  list<array{name: string, slug: string}>  $proposals
     */
    public function syncTagProposals(Model $model, array $proposals): void
    {
        $proposals = $this->normalizeProposals($proposals);

        DB::transaction(function () use ($model, $proposals): void {
            $tagIds = [];

            foreach ($proposals as $proposal) {
                $tagIds[] = Tag::firstOrCreate(
                    ['slug' => $proposal['slug']],
                    ['name' => $proposal['name']],
                )->id;
            }

            $model->tags()->sync($tagIds);
        });
    }

    /**
     * Every tag name in the hub, most-used first.
     *
     * Fed to a tagging agent so it reuses a tag the hub already carries rather
     * than inventing a near-duplicate of it. Names rather than slugs because the
     * name is what the model is asked to echo; {@see canonicalize} maps it back.
     *
     * @return list<string>
     */
    public function vocabulary(): array
    {
        return Tag::query()
            ->withCount('taggables')
            ->orderByDesc('taggables_count')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Every tag with its slug and usage count loaded, most-used first.
     *
     * The consolidation vocabulary, and deliberately not {@see vocabulary()}:
     * a merge proposal references tags by slug and has to justify which one
     * survives, so it needs the key and the usage count, not just the label a
     * per-meeting run echoes. Ordered the same way, so if the list ever has to
     * be truncated the tags a meeting is most likely to be about lead it.
     *
     * @return list<Tag>
     */
    public function inventory(): array
    {
        return Tag::query()
            ->withCount('taggables')
            ->orderByDesc('taggables_count')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Snap proposed tags onto the hub's own vocabulary.
     *
     * A model asked for a label it cannot verify invents one, and an invented
     * label that means an existing tag would become a second tag for one
     * concept — the drift this feature exists to avoid. A proposal whose name
     * or slug matches an existing tag is replaced by that tag's own pair, so
     * reuse does not depend on the model spelling it the way the hub did.
     * Everything else is passed through with its slug made safe to store.
     *
     * A bare string is read as both halves — the shape a slug-only caller and
     * an older stored list still use.
     *
     * @param  list<string|array{name?: string, slug?: string}>  $proposals
     * @return list<array{name: string, slug: string}>
     */
    public function canonicalize(array $proposals): array
    {
        $byName = [];
        $bySlug = [];

        foreach (Tag::query()->get(['name', 'slug']) as $tag) {
            $byName[$this->matchKey($tag->name)] ??= ['name' => $tag->name, 'slug' => $tag->slug];
            $bySlug[$this->matchKey($tag->slug)] ??= ['name' => $tag->name, 'slug' => $tag->slug];
        }

        $canonical = [];

        foreach ($proposals as $proposal) {
            if (is_string($proposal)) {
                $proposal = ['name' => $proposal, 'slug' => $proposal];
            }

            if (! is_array($proposal)) {
                continue;
            }

            $name = trim((string) ($proposal['name'] ?? ''));
            $slug = trim((string) ($proposal['slug'] ?? ''));

            if ($name === '' && $slug === '') {
                continue;
            }

            $match = $byName[$this->matchKey($name)] ?? $bySlug[$this->matchKey($slug)] ?? null;

            if ($match === null) {
                $slug = $this->slugify($slug !== '' ? $slug : $name);
                $name = $name !== '' ? $name : $slug;

                // A brand-new slug that already exists is still an existing
                // tag; matching it here rather than creating it is what keeps
                // one concept from ending up with two rows.
                $match = $bySlug[$this->matchKey($slug)] ?? ['name' => $name, 'slug' => $slug];
            }

            $canonical[$match['slug']] = ['name' => $match['name'], 'slug' => $match['slug']];
        }

        return array_values($canonical);
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
        return $this->applyFilter($model->newQuery(), $slugs);
    }

    /**
     * AND-filter applied onto an existing query (e.g. a list endpoint's
     * already-sorted query) — same semantics as {@see filterQuery}.
     *
     * @param  list<string>  $slugs
     */
    public function applyFilter(Builder $query, array $slugs): Builder
    {
        $slugs = $this->normalize($slugs);

        foreach ($slugs as $slug) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $slug));
        }

        return $query;
    }

    /**
     * The comparison key two spellings of one tag share.
     *
     * Deliberately more forgiving than the slug rule: a model writes the same
     * Persian label with an Arabic yeh, a stray zero-width joiner or a doubled
     * space often enough that an exact match would call them different tags.
     * Case, Arabic/Persian letter variants, zero-width joiners, tatweel, Arabic
     * diacritics and any run of space, hyphen or underscore all fold to one key
     * — so `مدیر عامل`, `مدیر-عامل` and `مدير عامل` agree on what they mean.
     */
    private function matchKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ي', 'ك', 'ى'], ['ی', 'ک', 'ی'], $value);
        $value = str_replace(["\u{200c}", "\u{0640}"], '', $value);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
        $value = preg_replace('/[\s\-_]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * The slug rule the tag boundary enforces, applied to a model's suggestion.
     *
     * The model is asked for a latin transliteration already; this is the net
     * under that ask, so a slug that arrives with a space, a punctuation mark
     * or a diacritic still stores as a valid, matchable key.
     *
     * @see TagRequest
     */
    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\s\-_]+/u', '-', $value) ?? $value;
        $value = preg_replace('/[^\p{Arabic}\p{Latin}\p{N}_-]+/u', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Lowercase and de-duplicate proposals by slug, defaulting a missing name
     * to the slug and dropping entirely empty entries.
     *
     * @param  list<array{name: string, slug: string}>  $proposals
     * @return list<array{name: string, slug: string}>
     */
    private function normalizeProposals(array $proposals): array
    {
        $normalized = [];

        foreach ($proposals as $proposal) {
            $slug = mb_strtolower(trim((string) ($proposal['slug'] ?? '')));

            if ($slug === '' || isset($normalized[$slug])) {
                continue;
            }

            $name = trim((string) ($proposal['name'] ?? ''));

            $normalized[$slug] = ['name' => $name !== '' ? $name : $slug, 'slug' => $slug];
        }

        return array_values($normalized);
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
