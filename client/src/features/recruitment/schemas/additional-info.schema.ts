import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const referenceSchema = z.object({
    full_name: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    relationship: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    workplace_phone: requiredString.max(15, "حداکثر ۱۵ کاراکتر."),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z.object({
    has_chronic_disease: z.boolean().optional(),
    chronic_disease_description: z.string().max(500).optional(),
    company_introduction_method: z.string().max(255).optional(),
    has_major_surgery: z.boolean().optional(),
    major_surgery_description: z.string().max(500).optional(),
    reason_for_joining: z.string().max(1000).optional(),
    has_disability: z.boolean().optional(),
    disability_description: z.string().max(500).optional(),
    can_travel: z.boolean().optional(),
    travel_description: z.string().max(500).optional(),
    has_criminal_record: z.boolean().optional(),
    criminal_record_description: z.string().max(500).optional(),
    hobbies: z.string().max(1000).optional(),
    references: z.array(referenceSchema).optional(),
    strengths_and_improvements: z.string().max(1000).optional(),
});

export type AdditionalInfoFormData = z.infer<typeof additionalInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    references: z.array(referenceSchema).optional(),
    reference_item: referenceSchema,
    has_chronic_disease: z.boolean().optional(),
    chronic_disease_description: z.string().max(500).optional(),
    has_major_surgery: z.boolean().optional(),
    major_surgery_description: z.string().max(500).optional(),
    has_disability: z.boolean().optional(),
    disability_description: z.string().max(500).optional(),
    can_travel: z.boolean().optional(),
    travel_description: z.string().max(500).optional(),
    has_criminal_record: z.boolean().optional(),
    criminal_record_description: z.string().max(500).optional(),
    reason_for_joining: z.string().max(1000).optional(),
    hobbies: z.string().max(1000).optional(),
    strengths_and_improvements: z.string().max(1000).optional(),
    company_introduction_method: z.string().max(255).optional(),
} as const;
