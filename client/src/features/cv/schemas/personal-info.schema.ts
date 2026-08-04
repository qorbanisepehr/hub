import { z } from "zod";

import {
    birthCertificateNumber,
    getAge,
    nationalId,
} from "@/lib/field-rules";
import { requiredText } from "@/lib/zod-primitives";

export const GENDER_VALUES = ["male", "female"] as const;
export const MARITAL_STATUS_VALUES = ["single", "married"] as const;
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

export const militaryStatusSchema = z
    .object({
        status: z.enum(MILITARY_STATUS_VALUES, {
            message: "وضعیت خدمت الزامی است.",
        }),
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
        birth_date: requiredString,
        birth_place: requiredText("این فیلد الزامی است.", 100),
        birth_certificate_number: birthCertificateNumber(),
        marital_status: z.enum(MARITAL_STATUS_VALUES, {
            message: "وضعیت تأهل الزامی است.",
        }),
        military_status: militaryStatusSchema.optional(),
        national_id: nationalId(),
    })
    .superRefine((data, ctx) => {
        if (data.gender === "male" && !data.military_status) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "وضعیت نظام وظیفه الزامی است.",
                path: ["military_status"],
            });
        }
    });

export type PersonalInfoFormData = z.infer<typeof personalInfoFieldSchema>;

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
    marital_status: z.enum(MARITAL_STATUS_VALUES, {
        message: "وضعیت تأهل الزامی است.",
    }),
    national_id: nationalId(),
    birth_place: requiredText("محل تولد الزامی است.", 100),
    birth_certificate_number: birthCertificateNumber(),
    military_status: militaryStatusSchema,
} as const;
