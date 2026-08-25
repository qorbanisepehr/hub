import type {
    ValidationSection,
    DocumentRequirement,
} from "@/lib/validation-helpers";

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

/** Statuses treated as «معافیت» (exemptions); their start date is required. */
export const MILITARY_EXEMPTION_STATUSES = [
    "guardian_exemption",
    "medical_exemption",
    "education_exemption",
    "leader_pardon",
] as const;

/** Statuses whose start date (from) is required: «امریه» plus exemptions. */
export const MILITARY_STATUS_REQUIRES_START_DATE: ReadonlySet<string> = new Set(
    ["amrieh", ...MILITARY_EXEMPTION_STATUSES],
);

export const MILITARY_STATUS_OTHER = "other";

/** Physical conditions that count as a disability, revealing the disability-type select. */
export const DISABLED_PHYSICAL_CONDITIONS: ReadonlySet<string> = new Set([
    "disabled",
    "severely_disabled",
]);

export const DEGREE_OPTIONS = [
    { value: "doctorate", label: "دکتری" },
    { value: "master", label: "کارشناسی ارشد" },
    { value: "bachelor", label: "کارشناسی" },
    { value: "associate", label: "فوق دیپلم" },
    { value: "diploma", label: "دیپلم" },
];

export const LANGUAGE_LEVEL_OPTIONS = [
    { value: "10", label: "۱۰٪ مقدماتی" },
    { value: "30", label: "۳۰٪ پایین‌تر از متوسط" },
    { value: "50", label: "۵۰٪ متوسط" },
    { value: "70", label: "۷۰٪ بالاتر از متوسط" },
    { value: "90", label: "۹۰٪ پیشرفته" },
    { value: "100", label: "۱۰۰٪ در حد زبان مادری" },
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

export const parseBoolean = (val: string): boolean => val === "true";

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
    {
        id: 0,
        label: "مشخصات فردی",
        description: "اطلاعات شخصی و شناسایی",
        key: "personal_info",
    },
    {
        id: 1,
        label: "اطلاعات تماس",
        description: "تلفن، ایمیل و آدرس",
        key: "contact_info",
    },
    {
        id: 2,
        label: "سوابق تحصیلی",
        description: "مدارک و سوابق تحصیلی",
        key: "education",
    },
    {
        id: 3,
        label: "سوابق شغلی",
        description: "تجربیات کاری قبلی",
        key: "work_experience",
    },
    {
        id: 4,
        label: "مهارت‌ها",
        description: "زبان‌ها و مهارت‌های نرم‌افزاری",
        key: "skills",
    },
    {
        id: 5,
        label: "آموزشی و تحقیقاتی",
        description: "دوره‌ها و پژوهش‌ها",
        key: "training",
    },
    {
        id: 6,
        label: "اطلاعات تکمیلی",
        description: "جزئیات اضافی",
        key: "additional_info",
    },
    {
        id: 7,
        label: "نوع درخواست همکاری",
        description: "شرایط و انتظارات شغلی",
        key: "job_request",
    },
    {
        id: 8,
        label: "بارگذاری مدارک",
        description: "آپلود فایل‌ها و مدارک",
        key: "documents",
    },
    {
        id: 9,
        label: "خلاصه و تأیید",
        description: "بررسی و ارسال نهایی",
        key: "summary",
    },
] as const;

export const DOC_CATEGORY_SLUGS = {
    NATIONAL_CARD: "national-card",
    BIRTH_CERTIFICATE: "birth-certificate",
    PERSONNEL_PHOTO: "personnel-photo",
    ACADEMIC_DEGREE: "academic-degree",
    LANGUAGE_CERTIFICATE: "language-certificate",
    COURSE_CERTIFICATES: "course-certificates",
    SKILL_CERTIFICATE: "skill-certificate",
    EMPLOYMENT_CERTIFICATE: "employment-certificate",
    RESEARCH_DOCUMENTS: "research-documents",
    RESUME: "resume",
    COVER_LETTER: "cover-letter",
    OTHER_DOCUMENTS: "other-documents",
    SIGNATURE_SAMPLE: "signature-sample",
    INQUIRY_RESULT: "inquiry-result",
} as const;

export const FIELD_KEY_LABELS: Record<string, string> = {
    front: "رو",
    back: "پشت",
    "page-1": "صفحه اول",
    "page-2": "صفحه دوم",
    "page-3": "صفحه آخر",
    photo: "تصویر پرسنلی",
};

export function getFieldKeyLabel(fieldKey?: string | null): string | null {
    if (!fieldKey) return null;
    return FIELD_KEY_LABELS[fieldKey] ?? fieldKey;
}

// Identity fields live on the real columns, not inside the JSONB section, so
// they need extra match prefixes when grouping errors by section.
const QUESTIONNAIRE_SECTION_IDENTITY_MATCH: Record<string, string[]> = {
    personal_info: ["first_name", "last_name"],
    contact_info: ["email", "mobile"],
};

export const QUESTIONNAIRE_VALIDATION_SECTIONS: ValidationSection[] =
    WIZARD_STEPS.filter((step) => step.key !== "summary").map((step) => ({
        key: step.key,
        label: step.label,
        match: [
            step.key,
            ...(QUESTIONNAIRE_SECTION_IDENTITY_MATCH[step.key] ?? []),
        ],
    }));

export const QUESTIONNAIRE_DOC_REQUIREMENTS: DocumentRequirement[] = [
    {
        slug: DOC_CATEGORY_SLUGS.NATIONAL_CARD,
        label: "کارت ملی",
        required: true,
        max: 1,
        requiredFields: [
            { fieldKey: "front", label: "رو" },
            { fieldKey: "back", label: "پشت" },
        ],
    },
    {
        slug: DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE,
        label: "شناسنامه",
        required: true,
        max: 1,
        requiredFields: [
            { fieldKey: "page-1", label: "صفحه اول" },
            { fieldKey: "page-2", label: "صفحه دوم" },
            { fieldKey: "page-3", label: "صفحه آخر" },
        ],
    },
    {
        slug: DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO,
        label: "تصویر پرسنلی",
        required: true,
        max: 1,
    },
    { slug: DOC_CATEGORY_SLUGS.RESUME, label: "رزومه", required: true },
    { slug: DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE, label: "مدرک تحصیلی" },
    { slug: DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE, label: "گواهینامه زبان" },
    { slug: DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES, label: "گواهینامه دوره" },
    {
        slug: DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
        label: "گواهی مهارت",
        max: 1,
    },
    {
        slug: DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE,
        label: "گواهی اشتغال به کار",
        max: 1,
    },
    {
        slug: DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS,
        label: "مدارک پژوهشی",
        max: 1,
    },
    { slug: DOC_CATEGORY_SLUGS.COVER_LETTER, label: "نامه معرفی", max: 1 },
    { slug: DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS, label: "سایر مدارک", max: 3 },
];
