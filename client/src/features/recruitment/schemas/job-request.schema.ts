import { z } from "zod";

import { numberField, requiredText, text } from "@/lib/zod-primitives";

export const EMPLOYMENT_TYPE_VALUES = ["full_time", "part_time"] as const;
export const PREFERRED_WORKPLACE_VALUES = ["tehran", "kerman", "site", "other"] as const;

export const jobRequestFieldSchema = z.object({
    employment_type: z.enum(EMPLOYMENT_TYPE_VALUES, { message: "نوع استخدام الزامی است." }),
    expected_monthly_salary: numberField(0, "حقوق نمی‌تواند منفی باشد."),
    minimum_hours_per_month: numberField(0, "ساعات کاری نمی‌تواند منفی باشد."),
    expected_hourly_salary: numberField(0, "حقوق ساعتی نمی‌تواند منفی باشد."),
    submitted_resume_before: z.boolean().optional(),
    interviewed_before: z.boolean().optional(),
    other_information: text(2000),
    accept_information: z.literal(true, { message: "باید اطلاعات را تأیید کنید." }),
    preferred_workplace: z.array(z.enum(PREFERRED_WORKPLACE_VALUES)).optional(),
    job_priority_1: requiredText("این فیلد الزامی است.", 100),
    job_priority_2: text(100),
    currently_employed: z.boolean().optional(),
    available_start_date: requiredText("این فیلد الزامی است."),
});

export type JobRequestFormData = z.infer<typeof jobRequestFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    employment_type: z.enum(EMPLOYMENT_TYPE_VALUES, { message: "نوع استخدام الزامی است." }),
    accept_information: z.literal(true, { message: "تأیید اطلاعات الزامی است." }),
    job_priority_1: requiredText("اولویت شغلی الزامی است.", 100),
    available_start_date: requiredText("تاریخ شروع به کار الزامی است."),
    expected_monthly_salary: numberField(0, "حقوق نمی‌تواند منفی باشد."),
    minimum_hours_per_month: numberField(0, "ساعات کاری نمی‌تواند منفی باشد."),
    expected_hourly_salary: numberField(0, "حقوق ساعتی نمی‌تواند منفی باشد."),
    preferred_workplace: z.array(z.enum(PREFERRED_WORKPLACE_VALUES)).optional(),
    job_priority_2: text(100, "حداکثر ۱۰۰ کاراکتر."),
    other_information: text(2000, "حداکثر ۲۰۰۰ کاراکتر."),
    submitted_resume_before: z.boolean().optional(),
    interviewed_before: z.boolean().optional(),
    currently_employed: z.boolean().optional(),
} as const;
