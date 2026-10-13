<?php

return [
    /*
     * Only what the API itself has to say. The summary's status words and
     * failure reasons are stored as stable keys precisely so the client can
     * render them in its own language, and it owns that copy — duplicating the
     * same vocabulary here would be a second translation of one contract.
     */
    'summary' => [
        'nothing_pending' => 'There is no summary awaiting a decision.',
        'stale_pending' => 'This proposal was replaced by a newer run since you opened it; re-read the meeting.',
        'bulk_queued' => ':count meetings queued. :skipped skipped.',
        'run_in_flight' => 'A summary run for this meeting is already under way; wait for it to finish before starting another.',
        'nothing_running' => 'There is no summary run here to stop.',
        'batch_cancelled' => 'Stopped :count runs that were still queued or under way.',
    ],
];
