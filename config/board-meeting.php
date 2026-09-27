<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Board Meeting Integration
    |--------------------------------------------------------------------------
    |
    | The Farzin SQL Server sync (decision K1). `driver` selects the source
    | implementation: 'fake' serves deterministic rows through the real
    | pipeline (tests / pre-production); 'sqlsrv' activates the real
    | read-only connection — credentials live in .env under the `sqlsrv`
    | database connection (never in code).
    |
    */

    'driver' => env('FARZIN_DRIVER', 'fake'),

    // Incremental sync reads rows edited after this many minutes ago on
    // the FIRST run when no sync state exists yet (safety window).
    'initial_lookback_days' => (int) env('FARZIN_INITIAL_LOOKBACK_DAYS', 30),

    // Approval-document archive (TCK parity: archive/approvals), scanned
    // for meeting numbers and attached docs by Jalali date. Phase 4 moves
    // this onto the hub Document system.
    'archive_disk' => env('FARZIN_ARCHIVE_DISK', 'local'),
    'archive_path' => env('FARZIN_ARCHIVE_PATH', 'archive/approvals'),

];
