export const BLOOD_GROUPS = [
    { value: "A+", label: "A+" },
    { value: "A-", label: "A-" },
    { value: "B+", label: "B+" },
    { value: "B-", label: "B-" },
    { value: "AB+", label: "AB+" },
    { value: "AB-", label: "AB-" },
    { value: "O+", label: "O+" },
    { value: "O-", label: "O-" },
];

export const GENDER_OPTIONS = [
    { value: "male", label: "مرد" },
    { value: "female", label: "زن" },
];

export const MARITAL_STATUS_OPTIONS = [
    { value: "single", label: "مجرد" },
    { value: "married", label: "متاهل" },
];

export const SPOUSE_EMPLOYMENT_OPTIONS = [
    { value: "employed", label: "شاغل" },
    { value: "housewife", label: "خانه دار" },
];

export const MILITARY_STATUS_OPTIONS = [
    { value: "completed", label: "پایان خدمت" },
    { value: "amrieh", label: "امریه" },
    { value: "guardian_exemption", label: "معافیت کفالت" },
    { value: "medical_exemption", label: "معافیت پزشکی" },
    { value: "education_exemption", label: "معافیت تحصیلی" },
    { value: "leader_pardon", label: "عفو رهبری" },
    { value: "service_purchase", label: "خرید خدمت" },
    { value: "other", label: "سایر" },
];

export const DEGREE_OPTIONS = [
    { value: "doctorate", label: "دکتری" },
    { value: "master", label: "کارشناسی ارشد" },
    { value: "bachelor", label: "کارشناسی" },
    { value: "associate", label: "فوق دیپلم" },
    { value: "diploma", label: "دیپلم" },
];

export const LANGUAGE_LEVEL_OPTIONS = [
    { value: "1", label: "۱" },
    { value: "2", label: "۲" },
    { value: "3", label: "۳" },
    { value: "4", label: "۴" },
];

export const SOFTWARE_LEVEL_OPTIONS = [
    { value: "1", label: "۱" },
    { value: "2", label: "۲" },
    { value: "3", label: "۳" },
    { value: "4", label: "۴" },
];

export const YES_NO_OPTIONS = [
    { value: "true", label: "بلی" },
    { value: "false", label: "خیر" },
];

export const EMPLOYMENT_TYPE_OPTIONS = [
    { value: "full_time", label: "تمام وقت" },
    { value: "part_time", label: "پاره وقت" },
];

export const CURRENTLY_EMPLOYED_OPTIONS = [
    { value: "true", label: "هستم" },
    { value: "false", label: "نیستم" },
];

export const PREFERRED_WORKPLACE_OPTIONS = [
    { value: "tehran", label: "دفتر تهران" },
    { value: "kerman", label: "دفتر کرمان" },
    { value: "site", label: "سایت" },
    { value: "other", label: "سایر" },
];

export const WIZARD_STEPS = [
    { id: 0, label: "مشخصات فردی", key: "personal_info" },
    { id: 1, label: "سوابق تحصیلی", key: "education" },
    { id: 2, label: "سوابق شغلی", key: "work_experience" },
    { id: 3, label: "مهارت‌ها", key: "skills" },
    { id: 4, label: "آموزشی و تحقیقاتی", key: "training" },
    { id: 5, label: "اطلاعات تکمیلی", key: "additional_info" },
    { id: 6, label: "نوع درخواست همکاری", key: "job_request" },
    { id: 7, label: "تأیید و ارسال", key: "verify" },
] as const;
