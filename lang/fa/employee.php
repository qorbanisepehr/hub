<?php

return [
    'deleted' => 'کارمند با موفقیت حذف شد.',
    'not_found' => 'کارمند مورد نظر یافت نشد.',
    'saved' => 'پروفایل کارمند ذخیره شد.',
    'submitted' => 'پروفایل کارمند با موفقیت ثبت شد.',
    'sections' => [
        'personal_info' => 'اطلاعات شخصی',
        'contact_info' => 'اطلاعات تماس',
        'employment' => 'اطلاعات شغلی',
        'education' => 'سوابق تحصیلی',
        'work_experience' => 'سوابق شغلی',
        'skills' => 'مهارت‌ها',
        'training' => 'دوره‌ها و آموزش‌ها',
        'additional_info' => 'اطلاعات تکمیلی',
        'social_insurance' => 'بیمه تأمین اجتماعی',
        'contracts' => 'قراردادها',
        'financial' => 'اطلاعات مالی',
        'supplementary_insurance' => 'بیمه تکمیلی',
        'dependents' => 'بستگان و افراد تحت تکفل',
        'document_inquiries' => 'استعلام مدارک',
    ],
    'dependents' => [
        'fields' => [
            'relationship_type' => 'نوع رابطه',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'id_number' => 'کد ملی',
            'gender' => 'جنسیت',
            'birth_date' => 'تاریخ تولد',
            'marriage_date' => 'تاریخ عقد',
        ],
        'validation' => [
            'birth_date_not_future' => 'تاریخ تولد وابسته نمی‌تواند در آینده باشد.',
        ],
        'field_label' => 'وابسته :n',
    ],
    'social_insurance' => [
        'fields' => [
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
        ],
        'validation' => [
            'end_date_before_start_date' => 'تاریخ پایان نباید زودتر از تاریخ شروع باشد.',
        ],
    ],
    'document_inquiries' => [
        'field_labels' => [
            'education_degree' => 'استعلام مدرک تحصیلی :n',
            'criminal-record' => 'استعلام عدم سوء پیشینه',
            'social-insurance' => 'استعلام بیمه تأمین اجتماعی',
        ],
        'validation' => [
            'invalid_education_index' => 'شناسه مدرک تحصیلی برای استعلام نامعتبر است.',
        ],
    ],
    'documents' => [
        'max_files_reached' => 'حداکثر :count فایل مجاز برای این نوع مدرک بارگذاری شده است.',
        'total_max_files_reached' => 'حداکثر :count فایل برای این کارمند بارگذاری شده است.',
        'trashed' => 'مدرک به سطل زباله منتقل شد.',
        'restored' => 'مدرک بازیابی شد.',
        'replaced' => 'مدرک با موفقیت جایگزین شد.',
        'invalid_category' => 'دسته‌بندی مدرک نامعتبر است.',
    ],
    'validation' => [
        'personnel_code_unique' => 'این کد پرسنلی قبلاً برای کارمند دیگری استفاده شده است.',
    ],
];
