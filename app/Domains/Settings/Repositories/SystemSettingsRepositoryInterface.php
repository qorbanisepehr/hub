<?php

namespace App\Domains\Settings\Repositories;

use App\Contracts\Settings\Setting;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Key-addressed storage for system configuration, one setting per key.
 *
 * Distinct from {@see SettingsRepositoryInterface}, which is a flat
 * key-to-array store for branding backed by files on disk. This one has a
 * group, a typed shape per key, and a private half per setting. They coexist
 * while branding is still file-backed; the point of this one is that the AI
 * registry has a single aggregate boundary and no query that crosses it, so
 * the row shape that suits it is one setting per provider rather than a
 * relational tree.
 *
 * Every read and write is addressed by a setting class plus a key, never by a
 * bare key, because the class is what turns a key into a shape. An addressable
 * key this store does not own is refused rather than stored — see
 * {@see Setting}.
 *
 * No caching here on purpose. Reads are a single indexed row lookup, and the
 * callers that read in a loop (the AI resolver) already hold their own
 * instances; a second cache would only add a way to read a stale provider.
 */
interface SystemSettingsRepositoryInterface
{
    /**
     * The setting stored under the given key, or null when nothing is.
     *
     * @template TSetting of Setting
     *
     * @param  class-string<TSetting>  $setting
     * @return TSetting|null
     */
    public function get(string $setting, string $key): ?object;

    /**
     * Every setting of the given kind, ordered by key.
     *
     * @template TSetting of Setting
     *
     * @param  class-string<TSetting>  $setting
     * @return list<TSetting>
     */
    public function all(string $setting): array;

    /**
     * Store the setting, replacing whatever was under its key.
     *
     * Replacing rather than merging is deliberate: a setting that left an old
     * model's entry in place because the new value happened to omit it would
     * keep serving a model an administrator believes they deleted.
     */
    /**
     * @param  class-string<TSetting>  $setting
     * @param  list<string>  $searchable  document fields a free-text term is matched against
     * @param  string  $sort  `key`, or a document field
     * @return LengthAwarePaginator<int, TSetting>
     */
    public function page(
        string $setting,
        ?string $term,
        array $searchable,
        string $sort,
        string $order,
        int $page,
        int $perPage,
    ): LengthAwarePaginator;

    /**
     * @param  class-string<TSetting>  $setting
     */
    public function put(Setting $setting, ?int $updatedBy = null): void;

    /** Drop whatever is stored under the given key. A no-op when nothing is. */
    public function forget(string $setting, string $key): void;

    /** Whether anything is stored under the given key. Never reads the value. */
    public function exists(string $setting, string $key): bool;

    /**
     * Unix timestamp of the last write for the given key, or 0 when there is
     * none. Matches {@see SettingsRepositoryInterface::lastModified()} so a
     * caller can ask either store the same question.
     */
    public function lastModified(string $setting, string $key): int;
}
