import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import type {
    DocumentRequirementSpec,
    ValidationSection,
} from "@/lib/validation-helpers";

export const employmentLabels: Record<string, string> = {
    official: "رسمی",
    contractual: "قراردادی",
    "project-based": "پروژه‌ای",
};

export const statusLabels: Record<string, string> = {
    active: "فعال",
    inactive: "غیرفعال",
    suspended: "تعلیق",
};

export const statusVariants: Record<
    string,
    "default" | "secondary" | "destructive"
> = {
    active: "default",
    inactive: "secondary",
    suspended: "destructive",
};

// Stored as the Persian form-option label (the same convention as the
// questionnaire), so there is no key → label mapping to keep.
export const EMPLOYEE_SECTIONS = [
    {
        key: "personal_info",
        label: "مشخصات فردی",
        description: "اطلاعات هویتی و شناسایی",
    },
    {
        key: "contact_info",
        label: "اطلاعات تماس",
        description: "تلفن، ایمیل و آدرس",
    },
    {
        key: "employment",
        label: "اطلاعات شغلی",
        description: "نوع استخدام، تاریخ و وضعیت",
    },
    {
        key: "education",
        label: "سوابق تحصیلی",
        description: "مدارک و سوابق تحصیلی",
    },
    {
        key: "social_insurance",
        label: "بیمه تأمین اجتماعی",
        description: "شماره بیمه و سوابق بیمه",
    },
    {
        key: "work_experience",
        label: "سوابق شغلی",
        description: "تجربیات کاری قبلی",
    },
    {
        key: "skills",
        label: "مهارت‌ها",
        description: "زبان‌ها و مهارت‌های نرم‌افزاری",
    },
    {
        key: "training",
        label: "آموزشی و تحقیقاتی",
        description: "دوره‌ها و پژوهش‌ها",
    },
    {
        key: "additional_info",
        label: "اطلاعات تکمیلی",
        description: "علایق، ارجاعات و نقاط قوت",
    },
    {
        key: "dependents",
        label: "بستگان و افراد تحت تکفل",
        description: "همسر، فرزندان و والدین",
    },
    {
        key: "document_inquiries",
        label: "استعلام مدارک",
        description: "استعلام تحصیلی، سوءپیشینه و بیمه",
    },
] as const;

export const EMPLOYEE_DOCUMENTS_TAB = {
    key: "documents",
    label: "مدارک",
    description: "مدارک و مستندات کارمند",
} as const;

export const EMPLOYEE_LINKED_USER_TAB = {
    key: "linked_user",
    label: "کاربر سیستمی مرتبط",
    description: "اطلاعات حساب کاربری متصل",
} as const;

export const EMPLOYEE_REVIEW_TAB = {
    key: "review",
    label: "بازبینی و تأیید",
    description: "بررسی خلاصه اطلاعات پروفایل",
} as const;

/**
 * Maps each employee section to the document categories shown inside its card
 * on the review tab and the read-only profile view. The same categories drive
 * the section/field placement rules for employee uploads.
 */
export const EMPLOYEE_SECTION_DOCS: { key: string; slugs: string[] }[] = [
    {
        key: "personal_info",
        slugs: [
            DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO,
            DOC_CATEGORY_SLUGS.NATIONAL_CARD,
            DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE,
        ],
    },
    {
        key: "employment",
        slugs: [DOC_CATEGORY_SLUGS.RESUME, DOC_CATEGORY_SLUGS.COVER_LETTER],
    },
    {
        key: "education",
        slugs: [DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE],
    },
    {
        key: "work_experience",
        slugs: [DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE],
    },
    {
        key: "skills",
        slugs: [
            DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE,
            DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES,
            DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
        ],
    },
    {
        key: "training",
        slugs: [
            DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES,
            DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS,
        ],
    },
    {
        key: "dependents",
        slugs: [DOC_CATEGORY_SLUGS.NATIONAL_CARD, DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE],
    },
    {
        key: "document_inquiries",
        slugs: [DOC_CATEGORY_SLUGS.INQUIRY_RESULT],
    },
];

/**
 * Per-category document requirements enforced in the review tab's
 * «بررسی اعتبار» summary. Identity docs are required because the employee
 * documents section always exposes an upload for them (personnel-photo has no
 * upload field for employees, so it is intentionally not required).
 */
export const EMPLOYEE_DOC_REQUIREMENTS: DocumentRequirementSpec[] = [
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
        slug: DOC_CATEGORY_SLUGS.SIGNATURE_SAMPLE,
        label: "نمونه امضا",
        required: true,
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

/**
 * Categories whose pages are required PER dependent row (mirrors
 * DependentsSection::dependentDocumentRequirements on the server — the page
 * counts themselves always come from the requirements endpoint, never
 * hardcoded here; only the display labels live on the client).
 */
export const DEPENDENT_DOC_CATEGORIES: {
    slug: string;
    label: string;
}[] = [
    { slug: DOC_CATEGORY_SLUGS.NATIONAL_CARD, label: "کارت ملی" },
    { slug: DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE, label: "شناسنامه" },
];

// Identity fields live on the real columns, not inside the JSONB section, so
// they need extra match prefixes when grouping errors by section.
const EMPLOYEE_SECTION_IDENTITY_MATCH: Record<string, string[]> = {
    personal_info: ["first_name", "last_name"],
    contact_info: ["email", "mobile"],
};

export const EMPLOYEE_VALIDATION_SECTIONS: ValidationSection[] =
    EMPLOYEE_SECTIONS.map((section) => ({
        key: section.key,
        label: section.label,
        match: [
            section.key,
            ...(EMPLOYEE_SECTION_IDENTITY_MATCH[section.key] ?? []),
        ],
    }));
