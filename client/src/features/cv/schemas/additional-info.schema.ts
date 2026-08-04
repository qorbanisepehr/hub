import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const referenceSchema = z.object({
    full_name: z.string().min(1, "نام و نام خانوادگی الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    relationship: z.string().min(1, "رابطه الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    workplace_phone: z.string().min(1, "تلفن محل کار الزامی است.").max(15, "حداکثر ۱۵ کاراکتر."),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z.object({
    hobbies: z.string().max(1000).optional(),
    references: z.array(referenceSchema).optional(),
    strengths_and_improvements: z.string().max(1000).optional(),
});

export type AdditionalInfoFormData = z.infer<typeof additionalInfoFieldSchema>;

export const fieldSchemas = {
    hobbies: z.string().max(1000, "حداکثر ۱۰۰۰ کاراکتر.").optional(),
    strengths_and_improvements: z.string().max(1000, "حداکثر ۱۰۰۰ کاراکتر.").optional(),
    references: z.array(referenceSchema).optional(),
    reference_item: referenceSchema,
} as const;
