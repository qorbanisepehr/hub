<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document (PDF / Word) rendering
    |--------------------------------------------------------------------------
    |
    | PDF/Word render is in-memory by nature (mPDF and PHPWord both build the
    | whole file before the first byte leaves the process), so tabular
    | document exports stay synchronous only up to `sync_row_limit` rows.
    | Above it the endpoint refuses with 422 and points the user at xlsx/csv
    | for large data. Queued rendering (job writes to a private disk, user
    | gets a download link) is deferred until the production queue driver is
    | confirmed — see the Import & Export plan §10.5.
    */

    'sync_row_limit' => (int) env('EXPORTS_SYNC_ROW_LIMIT', 500),

    /*
     * How large a bundle is allowed to be.
     *
     * These are deliberately NOT the document ZIP's caps (`documents.zip.*`),
     * because the same file count means very different work in the two cases.
     * A document archive is a handful of scans of one record — few files, each
     * one large. A bundle is many small rendered sheets across many records, so
     * five hundred of them is less work than a hundred scans, and holding both
     * to one number refused a whole registry's minutes over a limit written for
     * the other shape of thing.
     *
     * A bundle cap is also safer to raise than a document one: it is built by a
     * job against a bounded disk allowance rather than inside a request, so a
     * larger one costs waiting rather than a held worker.
     */
    'bundle' => [
        'max_entries' => (int) env('EXPORTS_BUNDLE_MAX_ENTRIES', 500),
        'max_bytes' => (int) env('EXPORTS_BUNDLE_MAX_BYTES', 1_073_741_824),
    ],

    /*
     * Archives too large to build inside a request.
     *
     * A bundle is rendered, zipped and streamed in one go, which holds a worker
     * for the whole build. That is fine for a handful of meetings and wrong for
     * a registry's worth, so anything above `sync_entry_limit` entries is built
     * by a job instead and announced when it is ready.
     *
     * Kept deliberately generous of small work: a bundle that downloads
     * immediately is one click and no notification, and turning every three-file
     * bundle into a queued job would make the common case the annoying one.
     */
    'queued' => [
        'sync_entry_limit' => (int) env('EXPORTS_SYNC_ENTRY_LIMIT', 25),

        /*
         * Finished archives live here until pruned. On the private disk, never
         * `public`: an archive holds meeting minutes, and a private disk is
         * also what the documents already use — so a bundle and its scans sit
         * behind the same kind of boundary.
         */
        'disk' => env('EXPORTS_DISK', 'local'),
        'directory' => 'exports',

        /*
         * How long a finished archive stays downloadable. A day is short on
         * purpose: these are minutes of a board meeting, not a record, and the
         * person who asked is notified the moment it is ready. `exports:prune`
         * deletes the rest on the same daily schedule as `audit:retention`.
         */
        'ttl_hours' => (int) env('EXPORTS_TTL_HOURS', 24),
    ],

    'documents' => [
        // Vazirmatn static TTFs ship in the repo so mPDF embeds correct
        // Persian glyphs; the metrics cache lands in storage/mpdf (writable).
        'font_dir' => resource_path('fonts/vazirmatn'),
        'font' => 'vazirmatn',
        'temp_dir' => storage_path('mpdf'),

        // Word documents just NAME the font (the reader's Word resolves it),
        // so Vazirmatn is only a preference there; Tahoma is the safe
        // Windows fallback for Persian when Vazirmatn is not installed.
        'docx_font' => env('EXPORTS_DOCX_FONT', 'Tahoma'),
    ],

];
