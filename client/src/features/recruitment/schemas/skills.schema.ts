import { z } from "zod";

import { numberField, requiredText, text } from "@/lib/zod-primitives";

export const languageSkillSchema = z.object({
    language: requiredText("نام زبان الزامی است.", 50),
    reading: numberField(1, "سطح خواندن الزامی است.", 4),
    writing: numberField(1, "سطح نوشتن الزامی است.", 4),
    speaking: numberField(1, "سطح صحبت کردن الزامی است.", 4),
    comprehension: numberField(1, "سطح درک مطلب الزامی است.", 4),
});

export type LanguageSkillFormData = z.infer<typeof languageSkillSchema>;

export const softwareSkillSchema = z.object({
    name: requiredText("نام نرم‌افزار الزامی است.", 100),
    level: numberField(1, "سطح مهارت الزامی است.", 4),
});

export type SoftwareSkillFormData = z.infer<typeof softwareSkillSchema>;

export const certificateSchema = z.object({
    title: requiredText("عنوان گواهینامه الزامی است.", 100),
    expire_at: text(),
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
    language: requiredText("نام زبان الزامی است.", 50),
    reading: numberField(1, "سطح خواندن الزامی است.", 4),
    writing: numberField(1, "سطح نوشتن الزامی است.", 4),
    speaking: numberField(1, "سطح صحبت کردن الزامی است.", 4),
    comprehension: numberField(1, "سطح درک مطلب الزامی است.", 4),
    software_skills: z
        .object({
            specialized: z.array(softwareSkillSchema).optional(),
            general: z.array(softwareSkillSchema).optional(),
        })
        .optional(),
    software_skill_item: softwareSkillSchema,
    software_skill_name: requiredText("نام نرم‌افزار الزامی است.", 100),
    certificates: z.array(certificateSchema).optional(),
    certificate_item: certificateSchema,
    certificate_title: requiredText("عنوان گواهینامه الزامی است.", 100),
    special_skills: z.array(text(100, "حداکثر ۱۰۰ کاراکتر.")).optional(),
    special_skill_item: requiredText("نام مهارت الزامی است.", 100),
} as const;
