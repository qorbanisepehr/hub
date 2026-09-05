import { z } from "zod";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

const contractRowSchema = z.object({
    start_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal(""))
        .default(""),
    end_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal(""))
        .default(""),
});

export const contractsFieldSchema = z.object({
    contracts: z.array(contractRowSchema).default([]),
});

export type ContractRow = z.infer<typeof contractRowSchema>;

/**
 * Submit-time refinement: the section is optional, but every existing row
 * must carry a start date. Mirrors the backend completion rule
 * required_with:contracts.
 */
export const contractsSubmitSchema = contractsFieldSchema.superRefine(
    (value, ctx) => {
        value.contracts.forEach((row, index) => {
            const today = new Date().toISOString().slice(0, 10);

            if (!row.start_date) {
                ctx.addIssue({
                    code: "custom",
                    path: ["contracts", index, "start_date"],
                    message: "تاریخ شروع قرارداد الزامی است.",
                });
            }

            if (
                row.start_date &&
                row.end_date &&
                row.end_date < row.start_date
            ) {
                ctx.addIssue({
                    code: "custom",
                    path: ["contracts", index, "end_date"],
                    message:
                        "تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.",
                });
            }

            if (row.start_date && row.start_date > today) {
                ctx.addIssue({
                    code: "custom",
                    path: ["contracts", index, "start_date"],
                    message: "تاریخ شروع نمی‌تواند در آینده باشد.",
                });
            }

            if (row.end_date && row.end_date > today) {
                ctx.addIssue({
                    code: "custom",
                    path: ["contracts", index, "end_date"],
                    message: "تاریخ پایان نمی‌تواند در آینده باشد.",
                });
            }
        });
    },
);

export type ContractsFormData = z.infer<typeof contractsFieldSchema>;

/** Default (draft) values for the contracts section. */
export function defaultContracts() {
    return {
        contracts: [] as Record<string, unknown>[],
    };
}

/**
 * Build the contracts section payload from the full form values. The section
 * passes through as-is (no real columns).
 */
export function toContractsPayload(values: {
    contracts?: unknown;
}): Record<string, unknown> {
    return (values.contracts as Record<string, unknown> | undefined) ?? {};
}