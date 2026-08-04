import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

export const referenceSchema = z.object({
    full_name: requiredText("نام و نام خانوادگی الزامی است.", 100),
    relationship: requiredText("رابطه الزامی است.", 50),
    workplace_phone: requiredText("تلفن محل کار الزامی است.", 15),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z
    .object({
        has_chronic_disease: z.boolean().optional(),
        chronic_disease_description: text(500),
        company_introduction_method: text(255),
        has_major_surgery: z.boolean().optional(),
        major_surgery_description: text(500),
        reason_for_joining: text(1000),
        has_disability: z.boolean().optional(),
        disability_description: text(500),
        can_travel: z.boolean().optional(),
        travel_description: text(500),
        has_criminal_record: z.boolean().optional(),
        criminal_record_description: text(500),
        hobbies: text(1000),
        references: z.array(referenceSchema).optional(),
        strengths_and_improvements: text(1000),
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
    chronic_disease_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
    has_major_surgery: z.boolean().optional(),
    major_surgery_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
    has_disability: z.boolean().optional(),
    disability_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
    can_travel: z.boolean().optional(),
    travel_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
    has_criminal_record: z.boolean().optional(),
    criminal_record_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
    reason_for_joining: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    hobbies: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    strengths_and_improvements: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    company_introduction_method: text(255, "حداکثر ۲۵۵ کاراکتر."),
} as const;
