import type { ValidationSection } from "@/lib/validation-helpers";

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

export const statusVariants: Record<string, "default" | "secondary" | "destructive"> = {
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
