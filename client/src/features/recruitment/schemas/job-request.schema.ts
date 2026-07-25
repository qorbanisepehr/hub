import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const EMPLOYMENT_TYPE_VALUES = ["full_time", "part_time"] as const;
export const PREFERRED_WORKPLACE_VALUES = ["tehran", "kerman", "site", "other"] as const;

export const jobRequestFieldSchema = z.object({
    employment_type: z.enum(EMPLOYMENT_TYPE_VALUES, { message: "نوع استخدام الزامی است." }),
    expected_monthly_salary: z.number().min(0).nullable().optional(),
    minimum_hours_per_month: z.number().min(0).nullable().optional(),
    expected_hourly_salary: z.number().min(0).nullable().optional(),
    submitted_resume_before: z.boolean().optional(),
    interviewed_before: z.boolean().optional(),
    other_information: z.string().max(2000).optional(),
    accept_information: z.literal(true, { message: "باید اطلاعات را تأیید کنید." }),
    preferred_workplace: z.array(z.enum(PREFERRED_WORKPLACE_VALUES)).optional(),
    job_priority_1: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    job_priority_2: z.string().max(100).optional(),
    currently_employed: z.boolean().optional(),
    available_start_date: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
});

export type JobRequestFormData = z.infer<typeof jobRequestFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    employment_type: z.enum(EMPLOYMENT_TYPE_VALUES, { message: "نوع استخدام الزامی است." }),
    accept_information: z.literal(true, { message: "باید اطلاعات را تأیید کنید." }),
    job_priority_1: requiredString,
    available_start_date: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
    expected_monthly_salary: z.number().min(0).nullable().optional(),
    minimum_hours_per_month: z.number().min(0).nullable().optional(),
    expected_hourly_salary: z.number().min(0).nullable().optional(),
    preferred_workplace: z.array(z.enum(PREFERRED_WORKPLACE_VALUES)).optional(),
    job_priority_2: z.string().max(100).optional(),
    other_information: z.string().max(2000).optional(),
    submitted_resume_before: z.boolean().optional(),
    interviewed_before: z.boolean().optional(),
    currently_employed: z.boolean().optional(),
} as const;
