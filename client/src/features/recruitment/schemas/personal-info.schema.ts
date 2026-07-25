import { z } from "zod";

export const GENDER_VALUES = ["male", "female"] as const;
export const BLOOD_GROUP_VALUES = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] as const;
export const MARITAL_STATUS_VALUES = ["single", "married"] as const;
export const SPOUSE_EMPLOYMENT_VALUES = ["employed", "housewife"] as const;
export const MILITARY_STATUS_VALUES = [
    "completed",
    "amrieh",
    "guardian_exemption",
    "medical_exemption",
    "education_exemption",
    "leader_pardon",
    "service_purchase",
    "other",
] as const;

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const militaryStatusSchema = z.object({
    status: z.enum(MILITARY_STATUS_VALUES, { message: "وضعیت خدمت الزامی است." }),
    organization: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    from: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
    to: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
    reason: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
});

export const personalInfoFieldSchema = z
    .object({
        gender: z.enum(GENDER_VALUES, { message: "جنسیت الزامی است." }),
        blood_group: z.enum(BLOOD_GROUP_VALUES, { message: "گروه خونی الزامی است." }),
        birth_date: requiredString,
        birth_place: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
        birth_certificate_number: requiredString.max(20, "حداکثر ۲۰ کاراکتر."),
        father_name: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
        religion: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
        marital_status: z.enum(MARITAL_STATUS_VALUES, { message: "وضعیت تأهل الزامی است." }),
        first_name_en: z.string().max(100).optional(),
        last_name_en: z.string().max(100).optional(),
        dependents_count: z.number().min(0).nullable().optional(),
        children_count: z.number().min(0).nullable().optional(),
        spouse_employment_status: z
            .enum(SPOUSE_EMPLOYMENT_VALUES, { message: "وضعیت اشتغال همسر الزامی است." })
            .optional(),
        military_status: militaryStatusSchema.optional(),
        national_id: z.string().length(10, "کد ملی باید ۱۰ رقم باشد."),
    })
    .superRefine((data, ctx) => {
        if (data.marital_status === "married" && !data.spouse_employment_status) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "وضعیت اشتغال همسر الزامی است.",
                path: ["spouse_employment_status"],
            });
        }
        if (data.gender === "male" && !data.military_status) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "وضعیت نظام وظیفه الزامی است.",
                path: ["military_status"],
            });
        }
    });

export type PersonalInfoFormData = z.infer<typeof personalInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    gender: z.enum(GENDER_VALUES, { message: "جنسیت الزامی است." }),
    birth_date: requiredString,
    marital_status: z.enum(MARITAL_STATUS_VALUES, { message: "وضعیت تأهل الزامی است." }),
    national_id: z.string().min(1, "کد ملی الزامی است."),
    blood_group: z.enum(BLOOD_GROUP_VALUES, { message: "گروه خونی الزامی است." }),
    birth_place: requiredString,
    birth_certificate_number: requiredString,
    father_name: requiredString,
    religion: requiredString,
    first_name_en: z.string().max(100).optional().or(z.literal("")),
    last_name_en: z.string().max(100).optional().or(z.literal("")),
    dependents_count: z.number().min(0).nullable().optional(),
    children_count: z.number().min(0).nullable().optional(),
    spouse_employment_status: z.enum(SPOUSE_EMPLOYMENT_VALUES, { message: "وضعیت اشتغال همسر الزامی است." }),
    military_status: militaryStatusSchema,
} as const;
