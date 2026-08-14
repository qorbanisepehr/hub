import { z } from "zod";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

export const employmentFieldSchema = z.object({
    employment_type: z
        .enum(["official", "contractual", "project-based"], {
            message: "نوع استخدام نامعتبر است",
        })
        .or(z.literal("")),
    hire_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal("")),
    employment_status: z
        .enum(["active", "inactive", "suspended"], {
            message: "وضعیت اشتغال نامعتبر است",
        })
        .or(z.literal("")),
});

export type EmploymentFormData = z.infer<typeof employmentFieldSchema>;

/**
 * Default (draft) values for the employment fields shared by the questionnaire
 * and employee profile forms.
 */
export function defaultEmployment() {
    return {
        employment_type: "",
        hire_date: "",
        employment_status: "",
    };
}
