import { z } from "zod";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

const historySchema = z.object({
    workshop_name: z.string().max(255).or(z.literal("")),
    workshop_code: z.string().max(50).or(z.literal("")),
    job_title: z.string().max(255).or(z.literal("")),
    start_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal("")),
    end_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal("")),
    description: z.string().max(1000).or(z.literal("")).default(""),
});

export const socialInsuranceFieldSchema = z.object({
    social_insurance_number: z
        .string()
        .max(30, "شماره بیمه حداکثر ۳۰ کاراکتر است")
        .or(z.literal(""))
        .default(""),

    // Backed by the `insurance_type` FormOption group (rendered as a select).
    insurance_status: z
        .string()
        .or(z.literal(""))
        .nullable()
        .transform((value) => value ?? ""),

    insurance_start_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal(""))
        .default(""),

    has_insurance_history: z.boolean(),

    branch_name: z.string().max(255).or(z.literal("")).nullable().transform((v) => v ?? ""),
    days_count: z.union([z.number(), z.string(), z.literal("")]).nullable().transform((v) => v ?? ""),

    monthly_breakdown: z.array(
        z.object({
            days: z
                .union([z.number(), z.string(), z.literal("")])
                .nullable()
                .transform((v) => v ?? ""),
            wage: z.string().max(30).or(z.literal("")).nullable().transform((v) => v ?? "").default(""),
        }),
    ).default([]),

    job_titles: z.array(
        z.object({
            insurance_number: z.string().max(30).or(z.literal("")).default(""),
            start_date: z
                .string()
                .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
                .or(z.literal(""))
                .default(""),
            job_title: z.string().max(255).or(z.literal("")).default(""),
            workshop_code: z.string().max(50).or(z.literal("")).default(""),
            workshop_name: z.string().max(255).or(z.literal("")).default(""),
        }),
    ).default([]),

    histories: z.array(historySchema),
});

export const socialInsuranceSubmitSchema =
    socialInsuranceFieldSchema.superRefine((value, ctx) => {
        if (!value.has_insurance_history) {
            if (value.histories.length > 0) {
                ctx.addIssue({
                    code: "custom",
                    path: ["histories"],
                    message:
                        "در صورت نداشتن سابقه بیمه، لیست سوابق باید خالی باشد.",
                });
            }

            return;
        }

        if (value.histories.length === 0) {
            ctx.addIssue({
                code: "custom",
                path: ["histories"],
                message: "حداقل یک سابقه بیمه وارد کنید.",
            });
            return;
        }

        const today = new Date().toISOString().slice(0, 10);

        value.histories.forEach((history, index) => {
            if (!history.start_date) {
                ctx.addIssue({
                    code: "custom",
                    path: ["histories", index, "start_date"],
                    message: "تاریخ شروع سابقه الزامی است.",
                });
                return;
            }

            if (history.start_date > today) {
                ctx.addIssue({
                    code: "custom",
                    path: ["histories", index, "start_date"],
                    message: "تاریخ شروع نمی‌تواند در آینده باشد.",
                });
            }

            if (history.end_date) {
                if (history.end_date > today) {
                    ctx.addIssue({
                        code: "custom",
                        path: ["histories", index, "end_date"],
                        message: "تاریخ پایان نمی‌تواند در آینده باشد.",
                    });
                }

                if (history.end_date < history.start_date) {
                    ctx.addIssue({
                        code: "custom",
                        path: ["histories", index, "end_date"],
                        message:
                            "تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.",
                    });
                }
            }
        });
    });

export type SocialInsuranceFormData = z.infer<
    typeof socialInsuranceFieldSchema
>;

/**
 * Default (draft) values for the social insurance section.
 */
export function defaultSocialInsurance() {
    return {
        social_insurance_number: "",
        insurance_status: "",
        insurance_start_date: "",
        has_insurance_history: false,
        branch_name: "",
        days_count: "",
        monthly_breakdown: [],
        job_titles: [],
        histories: [],
    };
}

/**
 * Build the social insurance section payload from the full form values. The
 * section is passed through as-is; the number is persisted into the real
 * `social_insurance_number` column at save time.
 */
export function toSocialInsurancePayload(values: {
    social_insurance?: unknown;
}): Record<string, unknown> {
    return (
        (values.social_insurance as Record<string, unknown> | undefined) ?? {}
    );
}
