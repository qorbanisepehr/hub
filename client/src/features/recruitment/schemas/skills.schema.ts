import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const languageSkillSchema = z.object({
    language: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    reading: z.number().min(1).max(4).nullable().optional(),
    writing: z.number().min(1).max(4).nullable().optional(),
    speaking: z.number().min(1).max(4).nullable().optional(),
    comprehension: z.number().min(1).max(4).nullable().optional(),
});

export type LanguageSkillFormData = z.infer<typeof languageSkillSchema>;

export const softwareSkillSchema = z.object({
    name: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    level: z.number().min(1).max(4).nullable().optional(),
});

export type SoftwareSkillFormData = z.infer<typeof softwareSkillSchema>;

export const certificateSchema = z.object({
    title: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
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
    software_skills: z
        .object({
            specialized: z.array(softwareSkillSchema).optional(),
            general: z.array(softwareSkillSchema).optional(),
        })
        .optional(),
    software_skill_item: softwareSkillSchema,
    certificates: z.array(certificateSchema).optional(),
    certificate_item: certificateSchema,
    special_skills: z.array(z.string().max(100)).optional(),
} as const;
