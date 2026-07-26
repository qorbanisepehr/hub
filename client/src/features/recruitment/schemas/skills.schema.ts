import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const languageSkillSchema = z.object({
    language: z.string().min(1, "نام زبان الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    reading: z.number().min(1, "سطح خواندن الزامی است.").max(4).nullable().optional(),
    writing: z.number().min(1, "سطح نوشتن الزامی است.").max(4).nullable().optional(),
    speaking: z.number().min(1, "سطح صحبت کردن الزامی است.").max(4).nullable().optional(),
    comprehension: z.number().min(1, "سطح درک مطلب الزامی است.").max(4).nullable().optional(),
});

export type LanguageSkillFormData = z.infer<typeof languageSkillSchema>;

export const softwareSkillSchema = z.object({
    name: z.string().min(1, "نام نرم‌افزار الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    level: z.number().min(1, "سطح مهارت الزامی است.").max(4).nullable().optional(),
});

export type SoftwareSkillFormData = z.infer<typeof softwareSkillSchema>;

export const certificateSchema = z.object({
    title: z.string().min(1, "عنوان گواهینامه الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    expire_at: z.string().nullable().optional(),
});

export type CertificateFormData = z.infer<typeof certificateSchema>;

export const skillsFieldSchema = z.object({
    languages: z.array(languageSkillSchema).optional(),
    software_skills: z
        .object({
            specialized: z.array(softwareSkillSchema).optional(),
            general: z.array(softwareSkillSchema).optional(),
        })
        .optional(),
    certificates: z.array(certificateSchema).optional(),
    special_skills: z.array(z.string().max(100)).optional(),
});

export type SkillsFormData = z.infer<typeof skillsFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    languages: z.array(languageSkillSchema).optional(),
    language_item: languageSkillSchema,
    language: z.string().min(1, "نام زبان الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    reading: z.number().min(1, "سطح خواندن الزامی است.").max(4).nullable().optional(),
    writing: z.number().min(1, "سطح نوشتن الزامی است.").max(4).nullable().optional(),
    speaking: z.number().min(1, "سطح صحبت کردن الزامی است.").max(4).nullable().optional(),
    comprehension: z.number().min(1, "سطح درک مطلب الزامی است.").max(4).nullable().optional(),
    software_skills: z
        .object({
            specialized: z.array(softwareSkillSchema).optional(),
            general: z.array(softwareSkillSchema).optional(),
        })
        .optional(),
    software_skill_item: softwareSkillSchema,
    software_skill_name: z.string().min(1, "نام نرم‌افزار الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    certificates: z.array(certificateSchema).optional(),
    certificate_item: certificateSchema,
    certificate_title: z.string().min(1, "عنوان گواهینامه الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    special_skills: z.array(z.string().max(100, "حداکثر ۱۰۰ کاراکتر.")).optional(),
    special_skill_item: z.string().min(1, "نام مهارت الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
} as const;
