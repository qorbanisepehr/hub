<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use Carbon\CarbonImmutable;

/**
 * Typed query for the unified inbox report (V2 §16/§32) — filters are part
 * of the report's contract, not a generic filter bag.
 */
final readonly class InboxQuery
{
    /**
     * @param  list<string>  $sources  Subset of source keys to fetch; empty = all registered.
     * @param  list<string>  $types  Document-type filter (unified vocabulary).
     * @param  list<string>  $statuses  Unified status vocabulary ("unseen"|"seen"|"done").
     */
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $limit = null,
        public array $sources = [],
        public array $types = [],
        public array $statuses = [],
    ) {}
}
