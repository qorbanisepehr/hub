import { z } from "zod";

import { isValidIdNumber } from "@/lib/field-rules";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

const dependentRowSchema = z.object({
    relationship_type: z.string().or(z.literal("")).default(""),
    first_name: z.string().max(100).or(z.literal("")).default(""),
    last_name: z.string().max(100).or(z.literal("")).default(""),
    id_number: z.string().max(10).or(z.literal("")).default(""),
    gender: z.string().or(z.literal("")).default(""),
    birth_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal(""))
        .default(""),
});

export const dependentsFieldSchema = z.object({
    dependents: z.array(dependentRowSchema).default([]),
});

export type DependentRow = z.infer<typeof dependentRowSchema>;

/**
 * Submit-time refinement: the section is optional as a whole, but every
 * existing row must be fully filled (mirrors the backend completion rules —
 * required_with:dependents).
 */
export const dependentsSubmitSchema = dependentsFieldSchema.superRefine(
    (value, ctx) => {
        const today = new Date().toISOString().slice(0, 10);

        value.dependents.forEach((row, index) => {
            const require = (field: keyof typeof row, message: string) => {
                if (!row[field]) {
                    ctx.addIssue({
                        code: "custom",
                        path: ["dependents", index, field],
                        message,
                    });
                }
            };

            require("relationship_type", "نوع رابطه الزامی است.");
            require("first_name", "نام وابسته الزامی است.");
            require("last_name", "نام خانوادگی وابسته الزامی است.");
            require("id_number", "کد ملی وابسته الزامی است.");
            require("gender", "جنسیت وابسته الزامی است.");
            require("birth_date", "تاریخ تولد وابسته الزامی است.");

            if (row.id_number && !/^\d{10}$/.test(row.id_number)) {
                ctx.addIssue({
                    code: "custom",
                    path: ["dependents", index, "id_number"],
                    message: "کد ملی باید دقیقاً ۱۰ رقم باشد.",
                });
            } else if (row.id_number && !isValidIdNumber(row.id_number)) {
                ctx.addIssue({
                    code: "custom",
                    path: ["dependents", index, "id_number"],
                    message: "کد ملی معتبر نیست.",
                });
            }

            if (row.birth_date && row.birth_date > today) {
                ctx.addIssue({
                    code: "custom",
                    path: ["dependents", index, "birth_date"],
                    message: "تاریخ تولد نمی‌تواند در آینده باشد.",
                });
            }
        });
    },
);

export type DependentsFormData = z.infer<typeof dependentsFieldSchema>;

/** Default (draft) values for the dependents section. */
export function defaultDependents() {
    return {
        dependents: [] as Record<string, unknown>[],
    };
}

/**
 * Build the dependents section payload from the full form values. The section
 * passes through as-is (no real columns).
 */
export function toDependentsPayload(values: {
    dependents?: unknown;
}): Record<string, unknown> {
    return (values.dependents as Record<string, unknown> | undefined) ?? {};
}
