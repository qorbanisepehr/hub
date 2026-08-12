import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";
import {
    optionEnumOptional,
    type OptionSource,
} from "@/features/form-options/schema";
import { DISABLED_PHYSICAL_CONDITIONS } from "@/features/questionnaire/constants";

export const referenceSchema = z.object({
    full_name: requiredText("نام و نام خانوادگی الزامی است.", 100),
    relationship: requiredText("رابطه الزامی است.", 50),
    workplace_phone: requiredText("تلفن محل کار الزامی است.", 15),
});

export type ReferenceFormData = z.infer<typeof referenceSchema>;

export const additionalInfoFieldSchema = z
    .object({
        hobbies: text(1000),
        references: z.array(referenceSchema).optional(),
        strengths_and_improvements: text(1000),
        physical_condition: text(50),
        disability_type: text(50),
    })
    .superRefine((data, ctx) => {
        if (
            data.physical_condition !== undefined &&
            DISABLED_PHYSICAL_CONDITIONS.has(data.physical_condition) &&
            !data.disability_type
        ) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "نوع معلولیت الزامی است.",
                path: ["disability_type"],
            });
        }
    });

export type AdditionalInfoFormData = z.infer<typeof additionalInfoFieldSchema>;

export const fieldSchemas = {
    hobbies: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    strengths_and_improvements: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
    references: z.array(referenceSchema).optional(),
    reference_item: referenceSchema,
} as const;

export type AdditionalInfoOptions = {
    physical_condition: OptionSource[];
    disability_type: OptionSource[];
};

/**
 * Per-field schemas extended with the two form-option backed fields
 * (`physical_condition`, `disability_type`), which validate against the
 * fetched option sets just like the personal-info enums.
 */
export function buildAdditionalInfoSchemas(options: AdditionalInfoOptions) {
    const physicalCondition = optionEnumOptional(
        options.physical_condition,
        "وضعیت جسمانی انتخاب‌شده معتبر نیست.",
    );
    const disabilityType = optionEnumOptional(
        options.disability_type,
        "نوع معلولیت انتخاب‌شده معتبر نیست.",
    );

    return {
        ...fieldSchemas,
        physical_condition: physicalCondition,
        disability_type: disabilityType,
    };
}
