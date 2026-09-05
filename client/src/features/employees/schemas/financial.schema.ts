import { z } from "zod";

const bankAccountRowSchema = z.object({
    bank_name: z.string().max(100).or(z.literal("")).default(""),
    account_number: z.string().max(30).or(z.literal("")).default(""),
    card_number: z.string().max(30).or(z.literal("")).default(""),
    shaba_number: z.string().max(30).or(z.literal("")).default(""),
});

export const financialFieldSchema = z.object({
    bank_accounts: z.array(bankAccountRowSchema).default([]),
});

export type BankAccountRow = z.infer<typeof bankAccountRowSchema>;

/**
 * Submit-time refinement: the section is optional, but every existing
 * account row must be fully filled (mirrors backend required_with:
 * bank_accounts).
 */
export const financialSubmitSchema = financialFieldSchema.superRefine(
    (value, ctx) => {
        value.bank_accounts.forEach((row, index) => {
            const require = (field: keyof typeof row, message: string) => {
                if (!row[field]) {
                    ctx.addIssue({
                        code: "custom",
                        path: ["bank_accounts", index, field],
                        message,
                    });
                }
            };

            require("bank_name", "نام بانک الزامی است.");
            require("account_number", "شماره حساب الزامی است.");
            require("card_number", "شماره کارت الزامی است.");
            require("shaba_number", "شماره شبا الزامی است.");
        });
    },
);

export type FinancialFormData = z.infer<typeof financialFieldSchema>;

/** Default (draft) values for the financial section. */
export function defaultFinancial() {
    return {
        bank_accounts: [] as Record<string, unknown>[],
    };
}

/**
 * Build the financial section payload from the full form values. The section
 * passes through as-is (no real columns).
 */
export function toFinancialPayload(values: {
    financial?: unknown;
}): Record<string, unknown> {
    return (values.financial as Record<string, unknown> | undefined) ?? {};
}