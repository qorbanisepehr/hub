import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const workExperienceRecordSchema = z
    .object({
        company: z.string().min(1, "نام شرکت الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
        location: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
        industry: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
        position: z.string().min(1, "سمت شغلی الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
        from: z.string().min(1, "تاریخ شروع الزامی است."),
        to: z.string().min(1, "تاریخ پایان الزامی است."),
        contract_type: z.string().max(50, "حداکثر ۵۰ کاراکتر.").optional(),
        phone: z.string().max(15, "حداکثر ۱۵ کاراکتر.").optional(),
        manager_name: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
        last_salary: z.number().min(0, "حقوق نمی‌تواند منفی باشد.").nullable().optional(),
        leave_reason: z.string().max(255, "حداکثر ۲۵۵ کاراکتر.").optional(),
    })
    .superRefine((data, ctx) => {
        if (data.from && data.to && data.to < data.from) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "تاریخ پایان نباید قبل از تاریخ شروع باشد.",
                path: ["to"],
            });
        }
    });

export type WorkExperienceRecordFormData = z.infer<typeof workExperienceRecordSchema>;

export const workExperienceFieldSchema = z.object({
    work_experiences: z.array(workExperienceRecordSchema).optional(),
    achievements: z.string().max(2000).optional(),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: z.string().max(500).optional(),
});

export type WorkExperienceFormData = z.infer<typeof workExperienceFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    work_experiences: z.array(workExperienceRecordSchema).optional(),
    work_experiences_item: workExperienceRecordSchema,
    company: z.string().min(1, "نام شرکت الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    position: z.string().min(1, "سمت شغلی الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    from: z.string().min(1, "تاریخ شروع الزامی است."),
    to: z.string().min(1, "تاریخ پایان الزامی است."),
    location: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
    industry: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
    contract_type: z.string().max(50, "حداکثر ۵۰ کاراکتر.").optional(),
    phone: z.string().max(15, "حداکثر ۱۵ کاراکتر.").optional(),
    manager_name: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
    last_salary: z.number().min(0, "حقوق نمی‌تواند منفی باشد.").nullable().optional(),
    leave_reason: z.string().max(255, "حداکثر ۲۵۵ کاراکتر.").optional(),
    achievements: z.string().max(2000, "حداکثر ۲۰۰۰ کاراکتر.").optional(),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: z.string().max(500, "حداکثر ۵۰۰ کاراکتر.").optional(),
} as const;
