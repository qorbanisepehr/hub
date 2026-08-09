import { z } from "zod";

import {
    birthCertificateNumber,
    getAge,
    nationalId,
} from "@/lib/field-rules";
import {
    optionEnum,
    placeEnum,
    type OptionSource,
    type PlaceOption,
} from "@/features/form-options/schema";
import { requiredText, text } from "@/lib/zod-primitives";
import { GENDER_MALE } from "@/features/recruitment/schemas/personal-info.schema";

export type PersonalInfoOptions = {
    gender: OptionSource[];
    marital_status: OptionSource[];
    military_status: OptionSource[];
    /** Active `province` group options for combined place validation. */
    province: PlaceOption[];
    /** Active `city` group options; the city label plus parent province label. */
    birth_place: PlaceOption[];
};

export const requiredString = requiredText("این فیلد الزامی است.");

export function buildPersonalInfoSchemas(options: PersonalInfoOptions) {
    const gender = optionEnum(options.gender, "جنسیت الزامی است.");
    const maritalStatus = optionEnum(options.marital_status, "وضعیت تأهل الزامی است.");
    const militaryStatusValue = optionEnum(
        options.military_status,
        "وضعیت خدمت الزامی است.",
    );
    const birthPlace = placeEnum(
        options.province,
        options.birth_place,
        "محل تولد الزامی است.",
    );

    const militaryStatusSchema = z
        .object({
            status: militaryStatusValue,
            organization: text(100),
            from: text(30),
            to: text(30),
            reason: text(255),
        });

    const personalInfoFieldSchema = z
        .object({
            gender,
            birth_date: requiredString,
            birth_place: birthPlace,
            birth_certificate_number: birthCertificateNumber(),
            marital_status: maritalStatus,
            military_status: militaryStatusSchema.optional(),
            national_id: nationalId(),
        })
        .superRefine((data, ctx) => {
            if (data.gender === GENDER_MALE && !data.military_status) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "وضعیت نظام وظیفه الزامی است.",
                    path: ["military_status"],
                });
            }
        });

    const fieldSchemas = {
        first_name: requiredText("نام الزامی است.", 100),
        last_name: requiredText("نام خانوادگی الزامی است.", 100),
        gender,
        birth_date: requiredText("تاریخ تولد الزامی است.").refine(
            (val) => {
                if (!val) return true;
                return getAge(val) >= 18;
            },
            "حداقل سن الزامی ۱۸ سال است.",
        ),
        marital_status: maritalStatus,
        national_id: nationalId(),
        birth_place: birthPlace,
        birth_certificate_number: birthCertificateNumber(),
        military_status: militaryStatusSchema,
    } as const;

    return { personalInfoFieldSchema, militaryStatusSchema, fieldSchemas };
}

export type PersonalInfoSchemas = ReturnType<typeof buildPersonalInfoSchemas>;

export type PersonalInfoFormData = z.infer<
    ReturnType<typeof buildPersonalInfoSchemas>["personalInfoFieldSchema"]
>;
