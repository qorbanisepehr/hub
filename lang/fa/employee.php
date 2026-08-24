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
        'dependents' => 'بستگان و افراد تحت تکفل',
    ],
    'dependents' => [
        'fields' => [
            'relationship_type' => 'نوع رابطه',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'id_number' => 'کد ملی',
            'gender' => 'جنسیت',
            'birth_date' => 'تاریخ تولد',
        ],
        'validation' => [
            'birth_date_not_future' => 'تاریخ تولد وابسته نمی‌تواند در آینده باشد.',
        ],
        'field_label' => 'وابسته :n',
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
