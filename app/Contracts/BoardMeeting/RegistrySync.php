<?php

namespace App\Contracts\BoardMeeting;

/**
 * The board meeting registry's single sync capability. The domain
 * publishes it; the integration that owns a source binds it. Nothing in
 * `App\Domains\BoardMeeting` knows which source — or any source — runs
 * behind it, so adding a second one is a container change (ADR-011 D6).
 */
interface RegistrySync
{
    /**
     * Pull from the bound source(s) and write the registry.
     *
     * @param  bool  $incremental  false to read everything instead of only
     *                             rows edited since the last successful sync.
     * @return array{
     *     records: array{synced: int, failed: int},
     *     meetings: array{created: int, refreshed: int, locked: int}
     * }
     */
    public function run(bool $incremental = true): array;
}
