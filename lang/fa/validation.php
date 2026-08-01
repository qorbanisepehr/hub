<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages.
    |
    */

    'accepted' => ':attribute باید پذیرفته شده باشد.',
    'accepted_if' => ':attribute باید پذیرفته شده باشد وقتی :other برابر با :value باشد.',
    'active_url' => ':attribute یک آدرس اینترنتی معتبر نیست.',
    'after' => ':attribute باید بعد از تاریخ :date باشد.',
    'after_or_equal' => ':attribute باید بعد از یا برابر با تاریخ :date باشد.',
    'alpha' => ':attribute فقط باید شامل حروف باشد.',
    'alpha_dash' => ':attribute فقط باید شامل حروف، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => ':attribute فقط باید شامل حروف و اعداد باشد.',
    'array' => ':attribute باید یک آرایه باشد.',
    'ascii' => ':attribute فقط باید شامل کاراکترهای ASCII باشد.',
    'before' => ':attribute باید قبل از تاریخ :date باشد.',
    'before_or_equal' => ':attribute باید قبل از یا برابر با تاریخ :date باشد.',
    'between' => [
        'array' => ':attribute باید بین :min و :max آیتم باشد.',
        'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => ':attribute باید بین :min و :max باشد.',
        'string' => ':attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => ':attribute باید true یا false باشد.',
    'can' => ':attribute مقدار غیرمجاز دارد.',
    'confirmed' => ':attribute با تاییدیه مطابقت ندارد.',
    'current_password' => 'رمز عبور صحیح نیست.',
    'date' => ':attribute یک تاریخ معتبر نیست.',
    'date_equals' => ':attribute باید برابر با تاریخ :date باشد.',
    'date_format' => ':attribute با فرمت :format مطابقت ندارد.',
    'decimal' => ':attribute باید :decimal رقم اعشاری داشته باشد.',
    'declined' => ':attribute باید رد شده باشد.',
    'declined_if' => ':attribute باید رد شده باشد وقتی :other برابر با :value باشد.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'digits' => ':attribute باید :digits رقم باشد.',
    'digits_between' => ':attribute باید بین :min و :max رقم باشد.',
    'dimensions' => ':attribute ابعاد تصویر نامعتبری دارد.',
    'distinct' => ':attribute دارای مقدار تکراری است.',
    'doesnt_end_with' => ':attribute نباید با :values پایان یابد.',
    'doesnt_start_with' => ':attribute نباید با :values شروع شود.',
    'email' => ':attribute باید یک آدرس ایمیل معتبر باشد.',
    'ends_with' => ':attribute باید با :values پایان یابد.',
    'enum' => ':attribute انتخاب نامعتبری دارد.',
    'exists' => ':attribute انتخاب شده نامعتبر است.',
    'file' => ':attribute باید یک فایل باشد.',
    'filled' => ':attribute الزامی است.',
    'gt' => [
        'array' => ':attribute باید بیشتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید بیشتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید بیشتر از :value باشد.',
        'string' => ':attribute باید بیشتر از :value کاراکتر باشد.',
    ],
    'gte' => [
        'array' => ':attribute باید حداقل :value آیتم داشته باشد.',
        'file' => ':attribute باید حداقل :value کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :value باشد.',
        'string' => ':attribute باید حداقل :value کاراکتر باشد.',
    ],
    'image' => ':attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب نامعتبری دارد.',
    'in_array' => ':attribute در :other معتبر نیست.',
    'integer' => ':attribute باید یک عدد صحیح باشد.',
    'ip' => ':attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => ':attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => ':attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => ':attribute باید یک رشته JSON معتبر باشد.',
    'lowercase' => ':attribute باید با حروف کوچک باشد.',
    'lt' => [
        'array' => ':attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید کمتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید کمتر از :value باشد.',
        'string' => ':attribute باید کمتر از :value کاراکتر باشد.',
    ],
    'lte' => [
        'array' => ':attribute باید حداکثر :value آیتم داشته باشد.',
        'file' => ':attribute باید حداکثر :value کیلوبایت باشد.',
        'numeric' => ':attribute باید حداکثر :value باشد.',
        'string' => ':attribute باید حداکثر :value کاراکتر باشد.',
    ],
    'mac_address' => ':attribute باید یک آدرس MAC معتبر باشد.',
    'max' => [
        'array' => ':attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => ':attribute نباید بیشتر از :max کیلوبایت باشد.',
        'numeric' => ':attribute نباید بیشتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'mimes' => 'فرمت :attribute مجاز نیست.',
    'mimetypes' => ':attribute باید فایلی از نوع :values باشد.',
    'min' => [
        'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'missing' => ':attribute باید وجود نداشته باشد.',
    'missing_if' => ':attribute باید وجود نداشته باشد وقتی :other برابر با :value باشد.',
    'missing_unless' => ':attribute باید وجود نداشته باشد مگر اینکه :other برابر با :value باشد.',
    'missing_with' => ':attribute باید وجود نداشته باشد وقتی :values وجود دارد.',
    'missing_with_all' => ':attribute باید وجود نداشته باشد وقتی :values وجود دارد.',
    'multiple_of' => ':attribute باید مضربی از :value باشد.',
    'not_in' => ':attribute انتخاب نامعتبری دارد.',
    'not_regex' => ':attribute فرمت نامعتبری دارد.',
    'nullable' => ':attribute می‌تواند خالی باشد.',
    'numeric' => ':attribute باید یک عدد باشد.',
    'password' => ':attribute نادرست است.',
    'present' => ':attribute باید وجود داشته باشد.',
    'present_if' => ':attribute باید وجود داشته باشد وقتی :other برابر با :value باشد.',
    'present_unless' => ':attribute باید وجود داشته باشد مگر اینکه :other برابر با :value باشد.',
    'present_with' => ':attribute باید وجود داشته باشد وقتی :values وجود دارد.',
    'present_with_all' => ':attribute باید وجود داشته باشد وقتی :values وجود دارد.',
    'regex' => ':attribute فرمت نامعتبری دارد.',
    'required' => ':attribute الزامی است.',
    'required_if' => ':attribute الزامی است وقتی :other برابر با :value باشد.',
    'required_unless' => ':attribute الزامی است مگر اینکه :other برابر با :value باشد.',
    'required_with' => ':attribute الزامی است وقتی :values وجود دارد.',
    'required_with_all' => ':attribute الزامی است وقتی :values وجود دارد.',
    'required_without' => ':attribute الزامی است وقتی :values وجود ندارد.',
    'required_without_all' => ':attribute الزامی است وقتی :values وجود ندارد.',
    'same' => ':attribute و :other باید مطابقت داشته باشند.',
    'size' => [
        'array' => ':attribute باید :size آیتم داشته باشد.',
        'file' => ':attribute باید :size کیلوبایت باشد.',
        'numeric' => ':attribute باید :size باشد.',
        'string' => ':attribute باید :size کاراکتر باشد.',
    ],
    'starts_with' => ':attribute باید با :values شروع شود.',
    'string' => ':attribute باید یک رشته باشد.',
    'timezone' => ':attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلا استفاده شده است.',
    'uploaded' => ':attribute آپلود نشد.',
    'uppercase' => ':attribute باید با حروف بزرگ باشد.',
    'url' => ':attribute باید یک آدرس اینترنتی معتبر باشد.',
    'ulid' => ':attribute باید یک ULID معتبر باشد.',
    'uuid' => ':attribute باید یک UUID معتبر باشد.',

    'min_file_size' => 'حجم فایل نباید کمتر از :size باشد.',
    'max_file_size' => 'حجم فایل نباید بیشتر از :size باشد.',
    'invalid_mime_type' => 'فرمت فایل مجاز نیست. فرمت‌های مجاز: :allowed',
    'invalid_image' => 'فایل تصویری معتبر نیست.',
    'min_width' => 'عرض تصویر باید حداقل :width پیکسل باشد.',
    'min_height' => 'ارتفاع تصویر باید حداقل :height پیکسل باشد.',
    'max_width' => 'عرض تصویر نباید بیشتر از :width پیکسل باشد.',
    'max_height' => 'ارتفاع تصویر نباید بیشتر از :height پیکسل باشد.',
    'aspect_ratio' => 'نسبت ابعاد تصویر باید :ratio باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        // ── InitQuestionnaireRequest ──
        'first_name' => [
            'required' => 'نام الزامی است.',
            'max' => 'حداکثر ۱۰۰ کاراکتر.',
        ],
        'last_name' => [
            'required' => 'نام خانوادگی الزامی است.',
            'max' => 'حداکثر ۱۰۰ کاراکتر.',
        ],
        'email' => [
            'required' => 'ایمیل الزامی است.',
            'email' => 'فرمت ایمیل نامعتبر است.',
            'max' => 'حداکثر ۲۵۵ کاراکتر.',
        ],
        'mobile' => [
            'required' => 'شماره موبایل الزامی است.',
            'max' => 'حداکثر ۱۵ کاراکتر.',
        ],
        // ── VerifyQuestionnaireRequest ──
        'otp' => [
            'required' => 'کد تأیید الزامی است.',
            'size' => 'کد تأیید باید ۶ رقم باشد.',
        ],
        // ── Personal Info ──
        'personal_info.gender' => [
            'required' => 'جنسیت الزامی است.',
            'in' => 'جنسیت معتبر نیست.',
        ],
        'personal_info.blood_group' => [
            'required' => 'گروه خونی الزامی است.',
            'in' => 'گروه خونی معتبر نیست.',
        ],
        'personal_info.birth_date' => [
            'required' => 'تاریخ تولد الزامی است.',
            'date_format' => 'فرمت تاریخ تولد نامعتبر است.',
        ],
        'personal_info.birth_place' => [
            'required' => 'محل تولد الزامی است.',
        ],
        'personal_info.birth_certificate_number' => [
            'required' => 'شماره شناسنامه الزامی است.',
            'regex' => 'شماره شناسنامه باید فقط شامل اعداد باشد.',
        ],
        'personal_info.father_name' => [
            'required' => 'نام پدر الزامی است.',
        ],
        'personal_info.religion' => [
            'required' => 'مذهب الزامی است.',
        ],
        'personal_info.marital_status' => [
            'required' => 'وضعیت تأهل الزامی است.',
            'in' => 'وضعیت تأهل معتبر نیست.',
        ],
        'personal_info.spouse_employment_status' => [
            'required_if' => 'وضعیت اشتغال همسر الزامی است.',
            'in' => 'وضعیت اشتغال همسر معتبر نیست.',
        ],
        'personal_info.spouse_job' => [
            'required_if' => 'شغل همسر الزامی است.',
        ],
        'personal_info.national_id' => [
            'required' => 'کد ملی الزامی است.',
        ],
        // ── Contact Info ──
        'contact_info.phone' => [
            'required' => 'تلفن ثابت الزامی است.',
        ],
        'contact_info.emergency_phone' => [
            'required' => 'تلفن اضطراری الزامی است.',
        ],
        'contact_info.address.postal_code' => [
            'required' => 'کد پستی الزامی است.',
        ],
        'contact_info.address.province' => [
            'required' => 'استان الزامی است.',
        ],
        'contact_info.address.city' => [
            'required' => 'شهر الزامی است.',
        ],
        'contact_info.address.address' => [
            'required' => 'آدرس الزامی است.',
        ],
        'personal_info.military_status.status' => [
            'required_with' => 'وضعیت نظام وظیفه الزامی است.',
            'in' => 'وضعیت نظام وظیفه معتبر نیست.',
        ],
        'personal_info.military_status.organization' => [
            'required_with' => 'سازمان الزامی است.',
        ],
        'personal_info.military_status.from' => [
            'required_with' => 'تاریخ شروع الزامی است.',
            'date_format' => 'فرمت تاریخ شروع نامعتبر است.',
        ],
        'personal_info.military_status.to' => [
            'required_with' => 'تاریخ پایان الزامی است.',
            'date_format' => 'فرمت تاریخ پایان نامعتبر است.',
        ],
        'personal_info.military_status.reason' => [
            'required_with' => 'دلیل الزامی است.',
        ],
        // ── Education ──
        'education.education_records' => [
            'required' => 'حداقل یک سوابق تحصیلی الزامی است.',
            'min' => 'حداقل یک سوابق تحصیلی الزامی است.',
        ],
        'education.education_records.*.degree' => [
            'required' => 'مدرک الزامی است.',
        ],
        'education.education_records.*.field' => [
            'required' => 'رشته تحصیلی الزامی است.',
        ],
        'education.education_records.*.institution' => [
            'required' => 'دانشگاه الزامی است.',
        ],
        'education.education_records.*.from' => [
            'required' => 'تاریخ شروع الزامی است.',
            'date_format' => 'فرمت تاریخ شروع نامعتبر است.',
        ],
        'education.education_records.*.to' => [
            'required' => 'تاریخ پایان الزامی است.',
            'date_format' => 'فرمت تاریخ پایان نامعتبر است.',
        ],
        'education.education_records.*.graduation_date' => [
            'required' => 'تاریخ فارغ‌التحصیلی الزامی است.',
        ],
        'education.education_records.*.gpa' => [
            'required' => 'معدل الزامی است.',
        ],
        'education.student_degree' => [
            'required_if' => 'مقطع تحصیلی الزامی است.',
        ],
        'education.student_field' => [
            'required_if' => 'رشته تحصیلی الزامی است.',
        ],
        'education.student_university' => [
            'required_if' => 'دانشگاه الزامی است.',
        ],
        'education.student_country' => [
            'required_if' => 'کشور الزامی است.',
        ],
        'education.student_city' => [
            'required_if' => 'شهر الزامی است.',
        ],
        'education.student_gpa' => [
            'required_if' => 'معدل الزامی است.',
        ],
        'education.study_start' => [
            'required_if' => 'تاریخ شروع تحصیل الزامی است.',
        ],
        'education.expected_graduation' => [
            'required_if' => 'تاریخ انتظار فارغ‌التحصیلی الزامی است.',
        ],
        'education.student_thesis_title' => [
            'required_if' => 'عنوان پایان‌نامه الزامی است.',
        ],
        // ── Work Experience ──
        'work_experience.work_experiences.*.company' => [
            'required' => 'نام شرکت الزامی است.',
        ],
        'work_experience.work_experiences.*.position' => [
            'required' => 'سمت شغلی الزامی است.',
        ],
        'work_experience.work_experiences.*.from' => [
            'required' => 'تاریخ شروع الزامی است.',
            'date_format' => 'فرمت تاریخ شروع نامعتبر است.',
        ],
        'work_experience.work_experiences.*.to' => [
            'required' => 'تاریخ پایان الزامی است.',
            'date_format' => 'فرمت تاریخ پایان نامعتبر است.',
        ],
        // ── Skills ──
        'skills.languages.*.language' => [
            'required' => 'نام زبان الزامی است.',
        ],
        'skills.software_skills.specialized.*.name' => [
            'required' => 'نام نرم‌افزار الزامی است.',
        ],
        'skills.software_skills.general.*.name' => [
            'required' => 'نام نرم‌افزار الزامی است.',
        ],
        'skills.certificates.*.title' => [
            'required' => 'عنوان گواهینامه الزامی است.',
        ],
        // ── Training ──
        'training.training_courses.*.course_name' => [
            'required' => 'نام دوره الزامی است.',
        ],
        'training.researches.*.title' => [
            'required' => 'عنوان تحقیق الزامی است.',
        ],
        // ── Additional Info ──
        'additional_info.references.*.full_name' => [
            'required' => 'نام کامل معرف الزامی است.',
        ],
        'additional_info.references.*.relationship' => [
            'required' => 'رابطه الزامی است.',
        ],
        'additional_info.references.*.workplace_phone' => [
            'required' => 'تلفن محل کار الزامی است.',
        ],
        // ── Job Request ──
        'job_request.employment_type' => [
            'required' => 'نوع استخدام الزامی است.',
            'in' => 'نوع استخدام معتبر نیست.',
        ],
        'job_request.accept_information' => [
            'required' => 'تأیید اطلاعات الزامی است.',
            'accepted' => 'باید اطلاعات را تأیید کنید.',
        ],
        'job_request.job_priority_1' => [
            'required' => 'اولویت شغلی الزامی است.',
        ],
        'job_request.available_start_date' => [
            'required' => 'تاریخ شروع به کار الزامی است.',
        ],
        'job_request.preferred_workplace.*' => [
            'in' => 'محل کار معتبر نیست.',
        ],
        // ── Public document upload ──
        'document_category_id' => [
            'required' => 'دسته‌بندی الزامی است.',
            'exists' => 'دسته‌بندی معتبر نیست.',
        ],
        'file' => [
            'required' => 'فایل الزامی است.',
        ],
    ],

    'attributes' => [
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'email' => 'ایمیل',
        'mobile' => 'شماره موبایل',
        'otp' => 'کد تأیید',
        'personal_info' => 'اطلاعات شخصی',
        'personal_info.gender' => 'جنسیت',
        'personal_info.blood_group' => 'گروه خونی',
        'personal_info.birth_date' => 'تاریخ تولد',
        'personal_info.birth_place' => 'محل تولد',
        'personal_info.birth_certificate_number' => 'شماره شناسنامه',
        'personal_info.father_name' => 'نام پدر',
        'personal_info.religion' => 'مذهب',
        'personal_info.marital_status' => 'وضعیت تأهل',
        'personal_info.first_name_en' => 'نام انگلیسی',
        'personal_info.last_name_en' => 'نام خانوادگی انگلیسی',
        'personal_info.dependents_count' => 'تعداد افراد تحت تکفل',
        'personal_info.children_count' => 'تعداد فرزندان',
        'personal_info.spouse_employment_status' => 'وضعیت اشتغال همسر',
        'personal_info.spouse_job' => 'شغل همسر',
        'personal_info.military_status' => 'وضعیت نظام وظیفه',
        'personal_info.military_status.status' => 'وضعیت',
        'personal_info.military_status.organization' => 'سازمان',
        'personal_info.military_status.from' => 'تاریخ شروع',
        'personal_info.military_status.to' => 'تاریخ پایان',
        'personal_info.military_status.reason' => 'دلیل',
        'personal_info.photo' => 'تصویر',
        'personal_info.national_id' => 'کد ملی',
        'contact_info.phone' => 'تلفن ثابت',
        'contact_info.emergency_phone' => 'تلفن اضطراری',
        'contact_info.address.postal_code' => 'کد پستی',
        'contact_info.address.province' => 'استان',
        'contact_info.address.city' => 'شهر',
        'contact_info.address.address' => 'آدرس',
        'contact_info.address.plaque' => 'پلاک',
        'contact_info.address.floor' => 'طبقه',
        'contact_info.address.unit' => 'واحد',
        'education' => 'سوابق تحصیلی',
        'education.education_records' => 'سوابق تحصیلی',
        'education.education_records.*.degree' => 'مدرک',
        'education.education_records.*.field' => 'رشته',
        'education.education_records.*.institution' => 'دانشگاه',
        'education.education_records.*.location' => 'محل',
        'education.education_records.*.from' => 'تاریخ شروع',
        'education.education_records.*.to' => 'تاریخ پایان',
        'education.education_records.*.thesis_title' => 'عنوان پایان‌نامه',
        'education.education_records.*.graduation_date' => 'تاریخ فارغ‌التحصیلی',
        'education.education_records.*.gpa' => 'معدل',
        'education.is_student' => 'وضعیت دانشجویی',
        'education.student_degree' => 'مقطع تحصیلی',
        'education.student_field' => 'رشته تحصیلی',
        'education.student_university' => 'دانشگاه',
        'education.student_country' => 'کشور',
        'education.student_city' => 'شهر',
        'education.student_semester' => 'ترم فعلی',
        'education.passed_units' => 'واحدهای گذرانده',
        'education.remaining_units' => 'واحدهای باقی‌مانده',
        'education.student_gpa' => 'معدل',
        'education.study_start' => 'تاریخ شروع تحصیل',
        'education.expected_graduation' => 'تاریخ انتظار فارغ‌التحصیلی',
        'education.thesis_submitted' => 'ارائه پایان‌نامه',
        'education.student_thesis_title' => 'عنوان پایان‌نامه',
        'education.free_days_per_week' => 'روزهای آزاد در هفته',
        'education.education_description' => 'توضیحات تحصیلی',
        'work_experience' => 'سوابق شغلی',
        'work_experience.work_experiences' => 'سوابق شغلی',
        'work_experience.work_experiences.*.company' => 'شرکت',
        'work_experience.work_experiences.*.location' => 'محل کار',
        'work_experience.work_experiences.*.industry' => 'صنعت',
        'work_experience.work_experiences.*.position' => 'سمت شغلی',
        'work_experience.work_experiences.*.from' => 'تاریخ شروع',
        'work_experience.work_experiences.*.to' => 'تاریخ پایان',
        'work_experience.work_experiences.*.contract_type' => 'نوع قرارداد',
        'work_experience.work_experiences.*.phone' => 'تلفن',
        'work_experience.work_experiences.*.manager_name' => 'نام مدیر',
        'work_experience.work_experiences.*.last_salary' => 'آخرین حقوق',
        'work_experience.work_experiences.*.leave_reason' => 'دلیل ترک',
        'work_experience.achievements' => 'دستاوردها',
        'work_experience.allow_contact_previous_managers' => 'اجازه تماس با مدیران قبلی',
        'work_experience.contact_restriction_description' => 'توضیحات محدودیت تماس',
        'skills' => 'مهارت‌ها',
        'skills.languages' => 'زبان‌ها',
        'skills.languages.*.language' => 'نام زبان',
        'skills.languages.*.reading' => 'خواندن',
        'skills.languages.*.writing' => 'نوشتن',
        'skills.languages.*.speaking' => 'صحبت کردن',
        'skills.languages.*.comprehension' => 'درک مطلب',
        'skills.software_skills' => 'مهارت‌های نرم‌افزاری',
        'skills.software_skills.specialized' => 'نرم‌افزارهای تخصصی',
        'skills.software_skills.specialized.*.name' => 'نام نرم‌افزار',
        'skills.software_skills.specialized.*.level' => 'سطح',
        'skills.software_skills.general' => 'نرم‌افزارهای عمومی',
        'skills.software_skills.general.*.name' => 'نام نرم‌افزار',
        'skills.software_skills.general.*.level' => 'سطح',
        'skills.certificates' => 'گواهینامه‌ها',
        'skills.certificates.*.title' => 'عنوان',
        'skills.certificates.*.expire_at' => 'تاریخ انقضا',
        'skills.special_skills' => 'مهارت‌های خاص',
        'training' => 'آموزش و تحقیقات',
        'training.training_courses' => 'دوره‌های آموزشی',
        'training.training_courses.*.course_name' => 'نام دوره',
        'training.training_courses.*.duration' => 'مدت زمان',
        'training.training_courses.*.institution' => 'سازمان برگزارکننده',
        'training.training_courses.*.held_at' => 'تاریخ برگزاری',
        'training.training_courses.*.certificate' => 'گواهینامه',
        'training.professional_memberships' => 'عضویت‌های حرفه‌ای',
        'training.researches' => 'تحقیقات',
        'training.researches.*.title' => 'عنوان',
        'additional_info' => 'اطلاعات تکمیلی',
        'additional_info.has_chronic_disease' => 'بیماری مزمن',
        'additional_info.chronic_disease_description' => 'توضیحات بیماری',
        'additional_info.company_introduction_method' => 'نحوه آشنایی با شرکت',
        'additional_info.has_major_surgery' => 'عمل جراحی سنگین',
        'additional_info.major_surgery_description' => 'توضیحات جراحی',
        'additional_info.reason_for_joining' => 'دلیل تمایل به همکاری',
        'additional_info.has_disability' => 'معلولیت',
        'additional_info.disability_description' => 'توضیحات معلولیت',
        'additional_info.can_travel' => 'امکان سفر',
        'additional_info.travel_description' => 'توضیحات سفر',
        'additional_info.has_criminal_record' => 'سوءسابقه کیفری',
        'additional_info.criminal_record_description' => 'توضیحات سوءسابقه',
        'additional_info.hobbies' => 'علاقه‌مندی‌ها',
        'additional_info.references' => 'معرف‌ها',
        'additional_info.references.*.full_name' => 'نام کامل',
        'additional_info.references.*.relationship' => 'رابطه',
        'additional_info.references.*.workplace_phone' => 'تلفن محل کار',
        'additional_info.strengths_and_improvements' => 'نقاط قوت و زمینه‌های قابل بهبود',
        'job_request' => 'درخواست همکاری',
        'job_request.employment_type' => 'نوع استخدام',
        'job_request.expected_monthly_salary' => 'حقوق ماهانه مورد انتظار',
        'job_request.minimum_hours_per_month' => 'حداقل ساعات کاری در ماه',
        'job_request.expected_hourly_salary' => 'حقوق ساعتی مورد انتظار',
        'job_request.submitted_resume_before' => 'ارسال رزومه قبلی',
        'job_request.interviewed_before' => 'مصاحبه قبلی',
        'job_request.other_information' => 'سایر اطلاعات',
        'job_request.accept_information' => 'تأیید اطلاعات',
        'job_request.preferred_workplace' => 'محل کار مورد نظر',
        'job_request.job_priority_1' => 'اولویت شغلی ۱',
        'job_request.job_priority_2' => 'اولویت شغلی ۲',
        'job_request.currently_employed' => 'شاغل در حال حاضر',
        'job_request.available_start_date' => 'تاریخ شروع به کار',
    ],

];
