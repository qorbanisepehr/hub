<?php

namespace App\Contracts\External;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use Carbon\CarbonImmutable;

/**
 * The inbox capability (V2 §8): "hand me unified items for this window".
 * A source system's integration adapter implements this — its mapper owns
 * the translation of letter/request/task vocabulary and external statuses
 * into UnifiedInboxItem (V2 §13, §61). The query's `sources`/`types`/
 * `statuses` filters belong to the report level; a gateway receives only
 * the window it must serve, keeping merge/sort/pagination report concerns.
 */
interface InboxGateway
{
    /**
     * @param  CarbonImmutable|null  $from  Inclusive window start (null = no lower bound).
     * @param  CarbonImmutable|null  $to  Inclusive window end (null = no upper bound).
     * @return list<UnifiedInboxItem>
     */
    public function inbox(?CarbonImmutable $from, ?CarbonImmutable $to): array;
}
