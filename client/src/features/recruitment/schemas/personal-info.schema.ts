import { z } from "zod";

import { numberField, requiredText, text } from "@/lib/zod-primitives";

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

export const requiredString = requiredText("این فیلد الزامی است.");

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
        organization: requiredText("سازمان الزامی است.", 100),
        from: requiredText("تاریخ شروع الزامی است."),
        to: requiredText("تاریخ پایان الزامی است."),
        reason: requiredText("دلیل الزامی است.", 255),
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
        birth_place: requiredText("این فیلد الزامی است.", 100),
        birth_certificate_number: requiredText("این فیلد الزامی است.", 20),
        father_name: requiredText("این فیلد الزامی است.", 100),
        religion: requiredText("این فیلد الزامی است.", 50),
        marital_status: z.enum(MARITAL_STATUS_VALUES, { message: "وضعیت تأهل الزامی است." }),
        first_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
        last_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
        dependents_count: numberField(0, "تعداد افراد تحت تکفل نمی‌تواند منفی باشد."),
        children_count: numberField(0, "تعداد فرزندان نمی‌تواند منفی باشد."),
        spouse_employment_status: z
            .enum(SPOUSE_EMPLOYMENT_VALUES, { message: "وضعیت اشتغال همسر الزامی است." })
            .optional(),
        spouse_job: text(100, "حداکثر ۱۰۰ کاراکتر."),
        military_status: militaryStatusSchema.optional(),
        national_id: requiredText("کد ملی الزامی است.", 10).refine(
            (v) => v.length === 10,
            "کد ملی باید ۱۰ رقم باشد.",
        ),
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
    first_name: requiredText("نام الزامی است.", 100),
    last_name: requiredText("نام خانوادگی الزامی است.", 100),
    gender: z.enum(GENDER_VALUES, { message: "جنسیت الزامی است." }),
    birth_date: requiredText("تاریخ تولد الزامی است.").refine(
        (val) => {
            if (!val) return true;
            return getAge(val) >= 18;
        },
        "حداقل سن الزامی ۱۸ سال است.",
    ),
    marital_status: z.enum(MARITAL_STATUS_VALUES, { message: "وضعیت تأهل الزامی است." }),
    national_id: requiredText("کد ملی الزامی است.", 10)
        .refine((v) => /^\d{10}$/.test(v), "کد ملی باید دقیقاً ۱۰ رقم باشد.")
        .refine(isValidNationalId, "کد ملی معتبر نیست."),
    blood_group: z.enum(BLOOD_GROUP_VALUES, { message: "گروه خونی الزامی است." }),
    birth_place: requiredText("محل تولد الزامی است.", 100),
    birth_certificate_number: requiredText("شماره شناسنامه الزامی است.", 20).refine(
        (v) => /^\d+$/.test(v),
        "شماره شناسنامه باید فقط شامل اعداد باشد.",
    ),
    father_name: requiredText("نام پدر الزامی است.", 100),
    religion: requiredText("مذهب الزامی است.", 50),
    first_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
    last_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
    dependents_count: numberField(0, "تعداد افراد تحت تکفل نمی‌تواند منفی باشد."),
    children_count: numberField(0, "تعداد فرزندان نمی‌تواند منفی باشد."),
    spouse_employment_status: z.enum(SPOUSE_EMPLOYMENT_VALUES, { message: "وضعیت اشتغال همسر الزامی است." }),
    spouse_job: requiredText("شغل همسر الزامی است.", 100),
    military_status: militaryStatusSchema,
} as const;
