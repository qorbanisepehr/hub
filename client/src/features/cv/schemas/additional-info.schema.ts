import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

export const referenceSchema = z.object({
    full_name: requiredText("نام و نام خانوادگی الزامی است.", 100),
    relationship: requiredText("رابطه الزامی است.", 50),
    workplace_phone: requiredText("تلفن محل کار الزامی است.", 15),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z.object({
    hobbies: text(1000),
    references: z.array(referenceSchema).optional(),
    strengths_and_improvements: text(1000),
});

export type AdditionalInfoFormData = z.infer<typeof additionalInfoFieldSchema>;

export const fieldSchemas = {
    hobbies: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    strengths_and_improvements: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    references: z.array(referenceSchema).optional(),
    reference_item: referenceSchema,
} as const;
