<?php

namespace App\Contracts\External;

use Throwable;

/**
 * The Farzin capability port: "hand me raw rows from the
 * organizational SQL Server". The integration drivers implement it; the
 * BoardMeeting registry import consumes it and knows nothing else about
 * where the rows came from.
 *
 * Rows stay in the source's own vocabulary (`EntityCode`, `dateSession`,
 * …) and shape — `array<string, mixed>` — because the port promises
 * "raw rows", not a parsed domain. Translating them is the integration's
 * job, and the domain DTOs the old adapter borrowed no longer exist.
 */
interface FarzinGateway
{
    /**
     * A4 letters, optionally only those edited after the given source
     * timestamp (incremental sync). Ordered by EntityCode for paging.
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime string, or null for everything.
     * @return list<array<string, mixed>>
     *
     * @throws Throwable when the source is unreachable.
     */
    public function letters(?string $afterLastEdit = null): array;

    /**
     * Board-session forms (`Entity_heyatModire`), optionally incremental.
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime string, or null for everything.
     * @return list<array<string, mixed>>
     *
     * @throws Throwable when the source is unreachable.
     */
    public function sessions(?string $afterLastEdit = null): array;
}
