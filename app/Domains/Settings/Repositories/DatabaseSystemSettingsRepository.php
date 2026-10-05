<?php

namespace App\Domains\Settings\Repositories;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The table-backed implementation of the typed settings store.
 *
 * Writes go through the query builder's `upsert` rather than through
 * `SystemSetting::save()`, for a reason documented on the model: a composite
 * primary key makes Eloquent's save path build a malformed where clause and
 * die. `upsert` also means a write is one statement, so two writers cannot
 * both see "no row" and both insert — the failure mode that left two rows for
 * one key earlier today.
 *
 * Values are pushed through the model instance first purely so the casts run:
 * `value` becomes JSON and `secrets` becomes encrypted ciphertext before they
 * reach the database, which means the same code that makes the round trip
 * decrypt is the code that cannot write plaintext into `getAttributes()`.
 */
final class DatabaseSystemSettingsRepository implements SystemSettingsRepositoryInterface
{
    public function get(string $setting, string $key): ?object
    {
        $row = $this->find($setting, $key);

        return $row === null
            ? null
            : $setting::fromStored($row->key, (array) $row->value, $row->secrets);
    }

    /**
     * Every setting of the given kind, ordered by key.
     *
     * Filtered by {@see Setting::handles()} rather than by a prefix the
     * repository knows, because a group holds more than one kind of setting and
     * the repository has no business knowing which prefix belongs to which. The
     * filter also means a new kind of key under the same group cannot break this
     * listing by being read as one of these.
     */
    public function all(string $setting): array
    {
        return $this->query($setting)
            ->orderBy('key')
            ->get()
            ->filter(fn (SystemSetting $row): bool => $setting::handles($row->key))
            ->map(fn (SystemSetting $row): object => $setting::fromStored(
                $row->key,
                (array) $row->value,
                $row->secrets,
            ))
            ->values()
            ->all();
    }

    public function put(Setting $setting, ?int $updatedBy = null): void
    {
        $this->assertHandled($setting::class, $setting->key());

        $row = new SystemSetting;
        $row->forceFill([
            'group' => $setting::group(),
            'key' => $setting->key(),
            'value' => $setting->toValue(),
            'secrets' => $setting->toSecrets(),
        ]);

        $now = Carbon::now();

        SystemSetting::query()->getQuery()->upsert(
            [[
                ...$row->getAttributes(),
                'created_at' => $now,
                'updated_at' => $now,
                'updated_by' => $updatedBy,
            ]],
            ['group', 'key'],
            ['value', 'secrets', 'updated_at', 'updated_by'],
        );
    }

    /**
     * Dropped through the query builder rather than `delete()` on the model,
     * which would need a key to build its where clause and there is only a
     * composite one here.
     */
    public function forget(string $setting, string $key): void
    {
        $this->assertHandled($setting, $key);

        $this->query($setting)->where('key', $key)->delete();
    }

    public function exists(string $setting, string $key): bool
    {
        return $this->find($setting, $key) !== null;
    }

    public function lastModified(string $setting, string $key): int
    {
        return $this->find($setting, $key)?->updated_at?->getTimestamp() ?? 0;
    }

    /**
     * One page of the settings of a kind, ordered by key unless asked otherwise.
     *
     * This is the SQL path, and it exists because a document's fields are still
     * columns *somewhere* — inside the jsonb. Reading a page without it means
     * loading every row to count them, and a list endpoint that loads the whole
     * group to answer "page 1 of 3" is a list endpoint wearing a costume.
     *
     * A field name means "a key inside the document" unless it is `key`, which
     * is the one that is a real column. Both are matched with `->>` rather than
     * by casting the column to text, and that choice is measured rather than
     * stylistic: `->>` returns the same string on Postgres jsonb and on SQLite's
     * json1 extension, while casting the column to text matches nothing on
     * SQLite for a URL (`json_encode` writes `https:\/\/…`, so the stored text
     * has no `//` to find) and is not even a legal operator on Postgres.
     *
     * @param  class-string<Setting>  $setting
     * @param  list<string>  $searchable  document fields a free-text term is matched against
     * @param  string  $sort  `key`, or a document field
     * @return LengthAwarePaginator<int, object>
     */
    public function page(
        string $setting,
        ?string $term,
        array $searchable,
        string $sort,
        string $order,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $paginator = $this->pageRows($setting, $term, $searchable, $sort, $order, $page, $perPage);

        $paginator->setCollection(
            $paginator->getCollection()
                ->filter(fn (SystemSetting $row): bool => $setting::handles($row->key))
                ->map(fn (SystemSetting $row): object => $setting::fromStored(
                    $row->key,
                    (array) $row->value,
                    $row->secrets,
                ))
                ->values(),
        );

        return $paginator;
    }

    /**
     * The rows behind {@see page()}, before they are turned into documents.
     *
     * @param  class-string<Setting>  $setting
     * @param  list<string>  $searchable
     * @return LengthAwarePaginator<int, SystemSetting>
     */
    private function pageRows(
        string $setting,
        ?string $term,
        array $searchable,
        string $sort,
        string $order,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = $this->query($setting);

        if (($prefix = $setting::keyPrefix()) !== null) {
            $query->where('key', 'like', $this->escapeLike($prefix).'%');
        }

        if ($term !== null && $term !== '') {
            // Wrapped by the connection's own grammar rather than spelled out
            // here. `->>` looks like the portable spelling and is not: the
            // sqlite grammar rewrites it as `json_extract(col, '$."> ''x''"')`,
            // reading the operator as a path arrow with a stray `>` on the end,
            // so the term is extracted from a path that does not exist. Handing
            // Laravel the `column->field` selector and letting it translate
            // produces `"value"->>'base_url'` on pgsql and
            // `json_extract("value", '$."base_url"')` on sqlite, which agree.
            $grammar = $query->getQuery()->getGrammar();

            $query->where(function (Builder $group) use ($grammar, $searchable, $term): void {
                foreach ($searchable as $field) {
                    $group->orWhereRaw(
                        sprintf('%s like ? escape \'\\\'', $grammar->wrap($this->column($field))),
                        ['%'.$this->escapeLike($term).'%'],
                    );
                }
            });
        }

        // `key` last, as a tiebreaker only, so a page whose order shifted
        // between requests cannot make the list look like it lost a row. Two
        // providers can share a driver and two agents can share a label.
        $query->orderBy($this->column($sort));

        if ($sort !== 'key') {
            $query->orderBy('key');
        }

        return $query->paginate(perPage: $perPage, page: $page)->withQueryString();
    }

    /**
     * A document field as a column reference, with the name checked first.
     *
     * Returned in Laravel's `column->path` selector syntax rather than as
     * finished SQL, so the connection's grammar gets to translate it — the one
     * job it already does correctly for every driver.
     *
     * The name is checked because this is the one place in the store where a
     * caller-supplied string reaches a column position, and a column cannot be
     * bound as a placeholder. The pattern is narrow on purpose: anything with a
     * quote, a space or a bracket in it is refused rather than escaped, because
     * an escaping routine for identifiers is one nobody could verify, and a
     * document field in this codebase is `snake_case` or it is a bug.
     */
    private function column(string $field): string
    {
        if ($field === 'key') {
            return 'key';
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a field a document can be read or ordered by.',
                $field,
            ));
        }

        return 'value->'.$field;
    }

    /**
     * `%` and `_` literal, so a filter typed as `gpt_4o` does not also match
     * `gptX4o`. Same reason, and the same explicit escape declaration, as the
     * query-backed search this stands in for — declared because sqlite has no
     * default LIKE escape, so an escaped term would match a literal backslash
     * there and never find the row.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private function find(string $setting, string $key): ?SystemSetting
    {
        $this->assertHandled($setting, $key);

        return $this->query($setting)->where('key', $key)->first();
    }

    /**
     * @param  class-string<Setting>  $setting
     * @return Builder<SystemSetting>
     */
    private function query(string $setting): Builder
    {
        return SystemSetting::query()->where('group', $setting::group());
    }

    /**
     * @param  class-string<Setting>  $setting
     */
    private function assertHandled(string $setting, string $key): void
    {
        if (! $setting::handles($key)) {
            throw InvalidSetting::unknownKey($setting, $key);
        }
    }
}
