import { z } from "zod";

const insuranceDependentRowSchema = z.object({
    first_name: z.string().max(100).or(z.literal("")).default(""),
    last_name: z.string().max(100).or(z.literal("")).default(""),
    relationship: z.string().or(z.literal("")).default(""),
});

export const supplementaryInsuranceFieldSchema = z.object({
    selected_bank_account: z.string().max(100).or(z.literal("")).default(""),
    insurance_dependents: z
        .array(insuranceDependentRowSchema)
        .default([]),
});

export type SupplementaryInsuranceDependentRow = z.infer<
    typeof insuranceDependentRowSchema
>;

/**
 * Submit-time refinement: the section is optional, but every existing
 * dependent row must be fully filled (mirrors backend required_with:
 * insurance_dependents).
 */
export const supplementaryInsuranceSubmitSchema =
    supplementaryInsuranceFieldSchema.superRefine((value, ctx) => {
        value.insurance_dependents.forEach((row, index) => {
            const require = (
                field: keyof typeof row,
                message: string,
            ) => {
                if (!row[field]) {
                    ctx.addIssue({
                        code: "custom",
                        path: ["insurance_dependents", index, field],
                        message,
                    });
                }
            };

            require("first_name", "نام بیمه‌شده الزامی است.");
            require("last_name", "نام خانوادگی بیمه‌شده الزامی است.");
            require("relationship", "نسبت بیمه‌شده الزامی است.");
        });
    });

export type SupplementaryInsuranceFormData = z.infer<
    typeof supplementaryInsuranceFieldSchema
>;

/** Default (draft) values for the supplementary insurance section. */
export function defaultSupplementaryInsurance() {
    return {
        selected_bank_account: "",
        insurance_dependents: [] as Record<string, unknown>[],
    };
}

/**
 * Build the supplementary insurance section payload from the full form
 * values. The section passes through as-is (no real columns).
 */
export function toSupplementaryInsurancePayload(values: {
    supplementary_insurance?: unknown;
}): Record<string, unknown> {
    return (
        (values.supplementary_insurance as
            | Record<string, unknown>
            | undefined) ?? {}
    );
}