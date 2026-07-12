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
        'attributes' => [],
    ],

];
