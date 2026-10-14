<?php

return [
    'generated_at' => 'تاریخ تولید',
    'row_count' => 'تعداد ردیف',

    // One label per registered renderer, so the bundle dialog can name a format
    // in the reader's language without a client change per renderer. A format
    // with no entry here falls back to its key rather than to a blank row.
    'formats' => [
        'pdf' => 'PDF',
        'docx' => 'Word',
        'md' => 'Markdown',
        'json' => 'JSON',
    ],
    'row_limit_exceeded' => 'حجم داده برای خروجی PDF/Word زیاد است؛ فیلترها را کم کنید یا از xlsx استفاده کنید.',
    // Refusals say what to narrow, not merely that something is too big: the
    // only way out is asking for less.
    'bundle_too_large_entries' => 'بسته شامل :count مورد است که بیش از حدِ :limit است؛ انتخاب یا فیلتر را محدودتر کنید.',
    'bundle_too_large_bytes' => 'حجم بسته :size بایت است و بیش از حدِ :limit بایت؛ انتخاب یا فیلتر را محدودتر کنید.',
    'documents' => [
        'titles' => [
            'employees' => 'فهرست کارکنان',
            'audit-logs' => 'گزارش لاگ فعالیت',
            'org-chart-roles' => 'چارت سازمانی (نقش‌ها)',
        ],
    ],
];
