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

    'print' => [
        'title' => 'Board meeting minutes',
        'yes' => 'Yes',
        'no' => 'No',
        'sections' => [
            'identity' => 'Meeting details',
            'attendees' => 'Attendees',
            'agenda' => 'Agenda',
            'minutes' => 'Minutes',
            'summary' => 'Summary',
            'approvals' => 'Resolutions',
            'tags' => 'Tags',
            'attachments' => 'Attachments',
        ],
        'fields' => [
            'note' => 'Note',
            'title' => 'Title',
            'held_on' => 'Meeting date (Gregorian)',
            'sequence' => 'Meeting number',
            'origin' => 'Source',
            'generated_at' => 'Generated at',
            'status' => 'Status',
            'error' => 'Reason',
            'provider' => 'Provider',
            'model' => 'Model',
            'executive_summary' => 'Executive summary',
            'topics' => 'Topics',
            'bullet_points' => 'Highlights',
            'key_decisions' => 'Key decisions',
            'suggested_tags' => 'Suggested tags',
        ],
        'columns' => [
            'value' => 'Item',
            'name' => 'Name',
            'role' => 'Role',
            'present' => 'Present',
            'position' => 'No.',
            'agenda_title' => 'Title',
            'registry_number' => 'Resolution no.',
            'document_title' => 'Document title',
            'tag' => 'Tag',
            'file_name' => 'File name',
            'size' => 'Size',
            'mime' => 'Type',
            'uploaded_at' => 'Uploaded at',
        ],
        'roles' => [
            'chair' => 'Chair',
            'member' => 'Member',
            'secretary' => 'Secretary',
            'observer' => 'Observer',
            'guest' => 'Guest',
        ],
        'statuses' => [
            'ok' => 'Complete',
            'partial' => 'Partial',
            'failed' => 'Failed',
            'pending' => 'Running',
            'none' => 'No summary',
            'cancelled' => 'Cancelled',
        ],
        'reasons' => [
            'no_structured_output' => 'The model did not return the requested structure.',
            'no_fields_returned' => 'None of the fields returned anything usable.',
            'not_configured' => 'No provider is configured.',
            'unavailable' => 'The provider did not answer.',
            'abandoned' => 'This run never reported.',
            'empty' => 'This field came back empty.',
        ],
        'notes' => [
            'accepted' => 'This summary has been accepted.',
            'pending' => 'This summary is an undecided proposal, not the record.',
            'none' => 'This meeting has no summary.',
        ],
    ],
];
