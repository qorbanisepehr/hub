<?php

namespace App\Contracts\External;

use App\Domains\BoardMeeting\Value\FarzinLetterRow;
use App\Domains\BoardMeeting\Value\FarzinSessionRow;

/**
 * The Farzin capability port (decision K1): "hand me raw rows from the
 * organizational SQL Server". The integration adapter implements this;
 * domain projections never touch the source connection.
 */
interface FarzinGateway
{
    /**
     * A4 letters, optionally only those edited after the given source
     * timestamp (incremental sync). Ordered by EntityCode for paging.
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime string, or null for everything.
     * @return list<FarzinLetterRow>
     */
    public function letters(?string $afterLastEdit = null): array;

    /**
     * Board-session forms (`Entity_heyatModire`), optionally incremental.
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime string, or null for everything.
     * @return list<FarzinSessionRow>
     */
    public function sessions(?string $afterLastEdit = null): array;
}
