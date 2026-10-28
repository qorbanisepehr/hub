<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use Carbon\CarbonImmutable;

/**
 * Typed query for the unified inbox report (V2 §16/§32) — filters are part
 * of the report's contract, not a generic filter bag. Serves the standard
 * list contract every list endpoint speaks: page/per_page, a single sort
 * key + direction, a global `filter` search needle, and exact-match column
 * filters with the `{field}_not` exclusion convention.
 */
final readonly class InboxQuery
{
    /**
     * @param  string  $sort  The only sortable business attribute (received_at).
     * @param  string  $filter  Global search needle across title/number/sender.
     * @param  ?string  $source  Exact source key; wins over the exclusion.
     * @param  ?string  $sourceNot  Excluded source key (the `{id}_not` convention).
     */
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public int $page = 1,
        public int $perPage = 50,
        public string $sort = 'received_at',
        public string $order = 'desc',
        public ?string $filter = null,
        public ?string $source = null,
        public ?string $sourceNot = null,
        public ?string $type = null,
        public ?string $typeNot = null,
        public ?string $status = null,
        public ?string $statusNot = null,
    ) {}

    /**
     * The subset of registered source keys to fetch: just the positive
     * filter when given (skip the other gateways entirely), otherwise an
     * empty set meaning "everything registered".
     *
     * @return list<string>
     */
    public function sourceKeySet(): array
    {
        return $this->source === null ? [] : [$this->source];
    }
}
