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

    /*
     * The printed sheet's own vocabulary.
     *
     * Not a second translation of the contract above: those keys are what the
     * API hands the client, and the client renders them. A PDF has no client,
     * so the words a reader of paper needs are declared here for the print
     * surface alone — and they are allowed to be more than the API's, because
     * the sheet also has to SAY which of the two summaries it printed (accepted
     * or merely proposed), which is a distinction the API expresses structurally
     * and paper can only express in words.
     */
    /*
     * The placeholders every template may use, whatever domain it belongs to.
     *
     * Their own group rather than entries in `print.fields`, because they are not
     * about a board meeting: a template that says `{{domain_label}}` is asking what
     * kind of document it is making, and the answer belongs to whoever is reading the
     * cheat sheet rather than to this domain's field list.
     */
    'print' => [
        'title' => 'صورتجلسهٔ هیئت مدیره',
        'yes' => 'بله',
        'no' => 'خیر',
        'sections' => [
            'identity' => 'اطلاعات جلسه',
            'attendees' => 'حاضران',
            'agenda' => 'دستور جلسه',
            'minutes' => 'صورتجلسه',
            'summary' => 'خلاصه',
            'approvals' => 'مصوبات',
            'tags' => 'برچسب‌ها',
            'attachments' => 'پیوست‌ها',
        ],
        'fields' => [
            'note' => 'توضیح',
            'title' => 'عنوان',
            'held_on' => 'تاریخ جلسه (میلادی)',
            'sequence' => 'شمارهٔ جلسه',
            'origin' => 'منبع',
            'generated_at' => 'زمان تولید',
            'status' => 'وضعیت',
            'error' => 'دلیل',
            'provider' => 'ارائه‌دهنده',
            'model' => 'مدل',
            'executive_summary' => 'چکیده',
            'topics' => 'موضوعات',
            'bullet_points' => 'نکات',
            'key_decisions' => 'تصمیم‌های کلیدی',
            'suggested_tags' => 'برچسب‌های پیشنهادی',
        ],
        'columns' => [
            'value' => 'مورد',
            'name' => 'نام',
            'role' => 'نقش',
            'present' => 'حاضر',
            'position' => 'ردیف',
            'agenda_title' => 'عنوان',
            'registry_number' => 'شمارهٔ مصوبه',
            'document_title' => 'عنوان سند',
            'tag' => 'برچسب',
            'file_name' => 'نام فایل',
            'size' => 'حجم',
            'mime' => 'نوع',
            'uploaded_at' => 'زمان بارگذاری',
        ],
        'roles' => [
            'chair' => 'رئیس',
            'member' => 'عضو',
            'secretary' => 'دبیر',
            'observer' => 'ناظر',
            'guest' => 'مهمان',
        ],
        'statuses' => [
            'ok' => 'کامل',
            'partial' => 'ناقص',
            'failed' => 'ناموفق',
            'pending' => 'در حال اجرا',
            'none' => 'بدون خلاصه',
            'cancelled' => 'لغو شده',
        ],
        'reasons' => [
            'no_structured_output' => 'مدل ساختار خواسته‌شده را برنگرداند.',
            'no_fields_returned' => 'هیچ‌کدام از فیلدها خروجی قابل‌قبولی نداشت.',
            'not_configured' => 'هیچ ارائه‌دهنده‌ای پیکربندی نشده است.',
            'unavailable' => 'ارائه‌دهنده پاسخ نداد.',
            'abandoned' => 'این اجرا هرگز گزارش نکرد.',
            'empty' => 'این فیلد خالی ماند.',
        ],
        'notes' => [
            'accepted' => 'این خلاصه پذیرفته شده است.',
            'pending' => 'این خلاصه پیشنهادیِ در انتظارِ تصمیم است و پذیرفته نشده.',
            'none' => 'برای این جلسه خلاصه‌ای وجود ندارد.',
        ],

        // The archive's index — what is inside, and which meeting each file is
        // for. Without it a zip of many meetings is a folder of unidentified
        // files, and the reader has to open things to find out.
        'bundle_manifest' => 'فهرست محتویات بسته',
        'bundle_items' => 'تعداد جلسات',
        'bundle_files' => 'فایل',
        'bundle_empty' => 'جلسه‌ای برای بسته‌بندی پیدا نشد.',
        'bundle_queued' => 'بسته بزرگ است؛ در پس‌زمینه ساخته می‌شود و آماده شد خبر می‌دهیم.',
        'bundle_gone' => 'این بسته دیگر در دسترس نیست؛ منقضی شده یا پاک شده است.',
        'bundle_stop_requested' => 'درخواست توقف ثبت شد؛ ساخت در مرز بعدی متوقف می‌شود.',
    ],

    /*
     * نام بخش‌های قابل انتخاب در قالب — جدا از `print.sections` که برچسبِ
     * چاپِ هر بخش است. اینجا نامِ ماشینی است و در پیام‌های اعتبارسنجی
     * مستقیم به کاربر نشان داده می‌شود، پس جدا بودنش از برچسبِ نمایشی لازم است:
     * برچسبِ «اطلاعات جلسه» برای کسی که دارد خطای `agenda` را می‌خواند کمکی
     * نمی‌کند.
     */
    'template' => [
        // The placeholders every template may use, whatever domain it belongs
        // to. Not part of `print.fields`: these are not about a board meeting,
        // they are what a GENERAL template needs in order to work on a domain
        // it was not written for.
        'general' => [
            'export_date' => 'تاریخ تهیهٔ خروجی',
            'created_date' => 'تاریخ ایجاد رکورد',
            'domain_label' => 'عنوان این حوزه',
            'entity_label' => 'عنوان این رکورد',
        ],

        'index' => 'قالب‌های خروجی',
        'read' => 'قالب جلسه',
        'saved' => 'قالب ذخیره شد.',
        'cleared' => 'قالب این جلسه پاک شد و به قالب دامنه برگشت.',
        'unknown_section' => 'بخشِ :sections یک بخش معتبر نیست.',
        'duplicate_section' => 'هر بخش فقط یک‌بار می‌تواند در قالب بیاید.',

        // کلیدواژه‌های کتابخانهٔ قالب Word — جدا از بخش‌های چاپی، چون این‌ها
        // دربارهٔ فایلی است که آپلود می‌شود نه دربارهٔ بخش‌های سند.
        'library' => 'قالب‌های Word',
        'fields' => 'نام‌های در دسترس',
        'deleted' => 'قالب حذف شد.',
        'not_found' => 'چنین قالبی یافت نشد.',
        'unknown_domain' => 'دامنهٔ :domains قالبی ندارد.',
        'file_required' => 'فایل قالب را انتخاب کنید.',
        'file_must_be_file' => 'فایل ارسالی یک فایل معتبر نیست.',
    ],
];
