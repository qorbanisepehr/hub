import { z } from "zod";

import { numberField, requiredText, text } from "@/lib/zod-primitives";

export const workExperienceRecordSchema = z
    .object({
        company: requiredText("نام شرکت الزامی است.", 100),
        location: text(100, "حداکثر ۱۰۰ کاراکتر."),
        industry: text(100, "حداکثر ۱۰۰ کاراکتر."),
        position: requiredText("سمت شغلی الزامی است.", 100),
        from: requiredText("تاریخ شروع الزامی است."),
        to: requiredText("تاریخ پایان الزامی است."),
        contract_type: text(50, "حداکثر ۵۰ کاراکتر."),
        phone: text(15, "حداکثر ۱۵ کاراکتر."),
        manager_name: text(100, "حداکثر ۱۰۰ کاراکتر."),
        last_salary: numberField(0, "حقوق نمی‌تواند منفی باشد."),
        leave_reason: text(255, "حداکثر ۲۵۵ کاراکتر."),
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
    achievements: text(2000),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: text(500),
});

export type WorkExperienceFormData = z.infer<typeof workExperienceFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    work_experiences: z.array(workExperienceRecordSchema).optional(),
    work_experiences_item: workExperienceRecordSchema,
    company: requiredText("نام شرکت الزامی است.", 100),
    position: requiredText("سمت شغلی الزامی است.", 100),
    from: requiredText("تاریخ شروع الزامی است."),
    to: requiredText("تاریخ پایان الزامی است."),
    location: text(100, "حداکثر ۱۰۰ کاراکتر."),
    industry: text(100, "حداکثر ۱۰۰ کاراکتر."),
    contract_type: text(50, "حداکثر ۵۰ کاراکتر."),
    phone: text(15, "حداکثر ۱۵ کاراکتر."),
    manager_name: text(100, "حداکثر ۱۰۰ کاراکتر."),
    last_salary: numberField(0, "حقوق نمی‌تواند منفی باشد."),
    leave_reason: text(255, "حداکثر ۲۵۵ کاراکتر."),
    achievements: text(2000, "حداکثر ۲۰۰۰ کاراکتر."),
    allow_contact_previous_managers: z.boolean().optional(),
    contact_restriction_description: text(500, "حداکثر ۵۰۰ کاراکتر."),
} as const;
