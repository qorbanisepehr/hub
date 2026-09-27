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
