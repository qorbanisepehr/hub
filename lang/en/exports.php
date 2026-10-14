<?php

return [
    'generated_at' => 'Generated at',
    'row_count' => 'Rows',
    'row_limit_exceeded' => 'The data is too large for a PDF/Word export; narrow the filters or use xlsx.',
    // Refusals say what to narrow, not merely that something is too big: the
    // only way out is asking for less.
    'bundle_too_large_entries' => 'The bundle holds :count items, over the limit of :limit; narrow your selection or filters.',
    'bundle_too_large_bytes' => 'The bundle is :size bytes, over the limit of :limit; narrow your selection or filters.',
    'documents' => [
        'titles' => [
            'employees' => 'Employee Directory',
            'audit-logs' => 'Activity Log Report',
            'org-chart-roles' => 'Organization Chart (Roles)',
        ],
    ],
];
