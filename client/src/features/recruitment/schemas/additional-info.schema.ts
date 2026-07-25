import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const referenceSchema = z.object({
    full_name: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    relationship: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    workplace_phone: requiredString.max(15, "حداکثر ۱۵ کاراکتر."),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z
    .object({
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
    })
    .superRefine((data, ctx) => {
        const conditions: [boolean | undefined, string, string][] = [
            [data.has_chronic_disease, "chronic_disease_description", "توضیحات بیماری مزمن الزامی است."],
            [data.has_major_surgery, "major_surgery_description", "توضیحات جراحی الزامی است."],
            [data.has_disability, "disability_description", "توضیحات معلولیت الزامی است."],
            [data.can_travel, "travel_description", "توضیحات سفر الزامی است."],
            [data.has_criminal_record, "criminal_record_description", "توضیحات سوءسابقه الزامی است."],
        ];

        for (const [condition, field, message] of conditions) {
            if (condition === true && !data[field as keyof typeof data]) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message,
                    path: [field],
                });
            }
        }
    });

export type AdditionalInfoFormData = z.infer<typeof additionalInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 * Descriptions stay optional here (required enforced at submit via superRefine).
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
