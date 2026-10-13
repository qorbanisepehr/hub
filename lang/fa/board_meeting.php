<?php

return [
    /*
     * Only what the API itself has to say. The summary's status words and
     * failure reasons are stored as stable keys precisely so the client can
     * render them in its own language, and it owns that copy — duplicating the
     * same vocabulary here would be a second translation of one contract.
     */
    'summary' => [
        'nothing_pending' => 'خلاصه‌ای در انتظار تصمیم وجود ندارد.',
        'stale_pending' => 'این پیشنهاد از وقتی که بازش کرده بودید با اجرای تازه‌تری عوض شده است؛ صفحه را دوباره بخوانید.',
        'bulk_queued' => ':count جلسه در صف قرار گرفت. :skipped مورد رد شد.',
        'run_in_flight' => 'اجرای این جلسه هنوز تمام نشده است؛ تا پایان همین اجرا امکان شروع اجرای تازه نیست.',
        'nothing_running' => 'اجرایی برای توقف وجود ندارد.',
        'batch_cancelled' => ':count اجرای در صف یا در حال اجرا متوقف شد.',
    ],
];
