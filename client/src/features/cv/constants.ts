import type {
    ValidationSection,
    DocumentRequirement,
} from "@/lib/validation-helpers";

import type { CvStatus } from "./types";

// Shared option lists are reused cross-domain from the recruitment feature to
// avoid duplicating the same Persian option labels in two places.
export {
    GENDER_OPTIONS,
    MARITAL_STATUS_OPTIONS,
    MILITARY_STATUS_OPTIONS,
    DEGREE_OPTIONS,
    LANGUAGE_LEVEL_OPTIONS,
    SOFTWARE_LEVEL_OPTIONS,
    YES_NO_OPTIONS,
    parseBoolean,
} from "@/features/recruitment/constants";

export const CV_ENTITY = "cv";

export const CV_WIZARD_STEPS = [
    { id: 0, label: "مشخصات فردی", description: "اطلاعات شخصی و شناسایی", key: "personal_info" },
    { id: 1, label: "اطلاعات تماس", description: "تلفن، ایمیل و آدرس", key: "contact_info" },
    { id: 2, label: "سوابق تحصیلی", description: "مدارک و سوابق تحصیلی", key: "education" },
    { id: 3, label: "سوابق شغلی", description: "تجربیات کاری قبلی", key: "work_experience" },
    { id: 4, label: "مهارت‌ها", description: "زبان‌ها و مهارت‌های نرم‌افزاری", key: "skills" },
    { id: 5, label: "آموزشی و تحقیقاتی", description: "دوره‌ها و پژوهش‌ها", key: "training" },
    { id: 6, label: "اطلاعات تکمیلی", description: "علایق، ارجاعات و نقاط قوت", key: "additional_info" },
    { id: 7, label: "بارگذاری مدارک", description: "رزومه و مدارک پیوست", key: "documents" },
    { id: 8, label: "خلاصه و تأیید", description: "بررسی و ارسال نهایی", key: "summary" },
] as const;

export const CV_DOC_CATEGORY_SLUGS = {
    RESUME: "resume",
    COVER_LETTER: "cover-letter",
    OTHER_DOCUMENTS: "other-documents",
} as const;

// Identity fields live on the real columns, not inside the JSONB section, so
// they need extra match prefixes when grouping errors by section.
const CV_SECTION_IDENTITY_MATCH: Record<string, string[]> = {
    personal_info: ["first_name", "last_name"],
    contact_info: ["email", "mobile"],
};

export const CV_VALIDATION_SECTIONS: ValidationSection[] = CV_WIZARD_STEPS.filter(
    (step) => step.key !== "summary",
).map((step) => ({
    key: step.key,
    label: step.label,
    match: [step.key, ...(CV_SECTION_IDENTITY_MATCH[step.key] ?? [])],
}));

export const CV_DOC_REQUIREMENTS: DocumentRequirement[] = [
    { slug: CV_DOC_CATEGORY_SLUGS.RESUME, label: "رزومه", required: true, max: 1 },
    { slug: CV_DOC_CATEGORY_SLUGS.COVER_LETTER, label: "نامه معرفی", max: 1 },
    { slug: CV_DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS, label: "سایر مدارک", max: 3 },
];

export const CV_STATUS_LABELS: Record<CvStatus, string> = {
    draft: "پیش‌نویس",
    submitted: "ارسال شده",
    approved: "تأیید شده",
    rejected: "رد شده",
};

export const CV_STATUS_BADGE_VARIANTS: Record<
    CvStatus,
    "default" | "secondary" | "outline" | "success" | "warning" | "destructive"
> = {
    draft: "secondary",
    submitted: "warning",
    approved: "success",
    rejected: "destructive",
};

export const CV_STATUS_OPTIONS = (Object.entries(CV_STATUS_LABELS) as [
    CvStatus,
    string,
][]).map(([value, label]) => ({ value, label }));
