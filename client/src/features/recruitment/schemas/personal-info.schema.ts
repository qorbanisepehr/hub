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

function isValidNationalId(val: string): boolean {
    if (!/^\d{10}$/.test(val)) return true;
    if (/^(\d)\1{9}$/.test(val)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(val[i]) * (10 - i);
    }
    const remainder = sum % 11;
    const control = remainder < 2 ? remainder : 11 - remainder;
    return parseInt(val[9]) === control;
}

function getAge(birthDate: string): number {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

export const militaryStatusSchema = z
    .object({
        status: z.enum(MILITARY_STATUS_VALUES, { message: "وضعیت خدمت الزامی است." }),
        organization: z.string().min(1, "سازمان الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
        from: z.string().min(1, "تاریخ شروع الزامی است."),
        to: z.string().min(1, "تاریخ پایان الزامی است."),
        reason: z.string().min(1, "دلیل الزامی است.").max(255, "حداکثر ۲۵۵ کاراکتر."),
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
        spouse_job: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional(),
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
        if (data.spouse_employment_status === "employed" && !data.spouse_job?.trim()) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "شغل همسر الزامی است.",
                path: ["spouse_job"],
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
    first_name: z.string().min(1, "نام الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    last_name: z.string().min(1, "نام خانوادگی الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    gender: z.enum(GENDER_VALUES, { message: "جنسیت الزامی است." }),
    birth_date: z
        .string()
        .min(1, "تاریخ تولد الزامی است.")
        .refine(
            (val) => {
                if (!val) return true;
                return getAge(val) >= 18;
            },
            "حداقل سن الزامی ۱۸ سال است.",
        ),
    marital_status: z.enum(MARITAL_STATUS_VALUES, { message: "وضعیت تأهل الزامی است." }),
    national_id: z
        .string()
        .min(1, "کد ملی الزامی است.")
        .regex(/^\d{10}$/, "کد ملی باید دقیقاً ۱۰ رقم باشد.")
        .refine(isValidNationalId, "کد ملی معتبر نیست."),
    blood_group: z.enum(BLOOD_GROUP_VALUES, { message: "گروه خونی الزامی است." }),
    birth_place: z.string().min(1, "محل تولد الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    birth_certificate_number: z
        .string()
        .min(1, "شماره شناسنامه الزامی است.")
        .regex(/^\d+$/, "شماره شناسنامه باید فقط شامل اعداد باشد.")
        .max(20, "حداکثر ۲۰ کاراکتر."),
    father_name: z.string().min(1, "نام پدر الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    religion: z.string().min(1, "مذهب الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    first_name_en: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional().or(z.literal("")),
    last_name_en: z.string().max(100, "حداکثر ۱۰۰ کاراکتر.").optional().or(z.literal("")),
    dependents_count: z.number().min(0, "تعداد افراد تحت تکفل نمی‌تواند منفی باشد.").nullable().optional(),
    children_count: z.number().min(0, "تعداد فرزندان نمی‌تواند منفی باشد.").nullable().optional(),
    spouse_employment_status: z.enum(SPOUSE_EMPLOYMENT_VALUES, { message: "وضعیت اشتغال همسر الزامی است." }),
    spouse_job: z.string().min(1, "شغل همسر الزامی است.").max(100, "حداکثر ۱۰۰ کاراکتر."),
    military_status: militaryStatusSchema,
} as const;
