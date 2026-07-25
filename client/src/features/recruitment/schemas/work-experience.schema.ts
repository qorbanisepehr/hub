import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const workExperienceRecordSchema = z.object({
    company: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    location: z.string().max(100).optional(),
    industry: z.string().max(100).optional(),
    position: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    from: requiredString,
    to: requiredString,
    contract_type: z.string().max(50).optional(),
    phone: z.string().max(15).optional(),
    manager_name: z.string().max(100).optional(),
    last_salary: z.number().min(0).nullable().optional(),
    leave_reason: z.string().max(255).optional(),
});

export type WorkExperienceRecordFormData = z.infer<typeof workExperienceRecordSchema>;

export const workExperienceFieldSchema = z.object({
    work_experiences: z.array(workExperienceRecordSchema).optional(),
    achievements: z.string().max(2000).optional(),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: z.string().max(500).optional(),
});

export type WorkExperienceFormData = z.infer<typeof workExperienceFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    work_experiences: z.array(workExperienceRecordSchema).optional(),
    work_experiences_item: workExperienceRecordSchema,
    achievements: z.string().max(2000).optional(),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: z.string().max(500).optional(),
} as const;
