import { z } from "zod";

import {
    birthCertificateNumber,
    getAge,
    idNumber,
} from "@/lib/field-rules";
import {
    optionEnum,
    optionEnumOptional,
    placeEnum,
    type OptionSource,
    type PlaceOption,
} from "@/features/form-options/schema";
import { numberField, requiredText, text } from "@/lib/zod-primitives";
import {
    MILITARY_STATUS_OTHER,
    MILITARY_STATUS_REQUIRES_START_DATE,
} from "@/features/questionnaire/constants";

// Special values with structural meaning in the form logic (e.g. "when married
// the spouse employment field becomes required"). Form sections persist the
// stable value key, so these mirror the option value keys and the server-side rules.
export const GENDER_MALE = "male";
export const GENDER_FEMALE = "female";
export const MARITAL_SINGLE = "single";
export const MARITAL_MARRIED = "married";
export const SPOUSE_EMPLOYED = "employed";

export type PersonalInfoOptions = {
    gender: OptionSource[];
    blood_group: OptionSource[];
    marital_status: OptionSource[];
    spouse_employment_status: OptionSource[];
    military_status: OptionSource[];
    religion: OptionSource[];
    religion_sect: OptionSource[];
    /** Active `province` group options for combined place validation. */
    province: PlaceOption[];
    /** Active `city` group options; the city label plus parent province label. */
    birth_place: PlaceOption[];
};

export const requiredString = requiredText("این فیلد الزامی است.");

export function buildPersonalInfoSchemas(options: PersonalInfoOptions) {
    const gender = optionEnum(options.gender, "جنسیت الزامی است.");
    const bloodGroup = optionEnum(options.blood_group, "گروه خونی الزامی است.");
    const maritalStatus = optionEnum(options.marital_status, "وضعیت تأهل الزامی است.");
    const spouseEmployment = optionEnumOptional(
        options.spouse_employment_status,
        "وضعیت اشتغال همسر الزامی است.",
    );
    const militaryStatusValue = optionEnum(
        options.military_status,
        "وضعیت خدمت الزامی است.",
    );
    const religion = optionEnum(options.religion, "دین الزامی است.");
    const religionSect = optionEnumOptional(
        options.religion_sect,
        "مذهب الزامی است.",
    );
    const birthPlace = placeEnum(
        options.province,
        options.birth_place,
        "محل تولد الزامی است.",
    );

    const militaryStatusSchema = z
        .object({
            status: militaryStatusValue,
            organization: requiredText("سازمان الزامی است.", 100),
            from: text(30),
            to: requiredText("تاریخ پایان الزامی است.", 30),
            reason: text(255),
        })
        .superRefine((data, ctx) => {
            if (MILITARY_STATUS_REQUIRES_START_DATE.has(data.status) && !data.from) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "تاریخ شروع الزامی است.",
                    path: ["from"],
                });
            }
            if (data.status === MILITARY_STATUS_OTHER && !data.reason) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "توضیحات الزامی است.",
                    path: ["reason"],
                });
            }
            if (data.from && data.to && data.to < data.from) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "تاریخ پایان نباید قبل از تاریخ شروع باشد.",
                    path: ["to"],
                });
            }
        });

    const personalInfoFieldSchema = z
        .object({
            gender,
            blood_group: bloodGroup,
            birth_date: requiredString,
            birth_place: birthPlace,
            birth_certificate_number: birthCertificateNumber(),
            father_name: requiredText("این فیلد الزامی است.", 100),
            religion,
            religion_sect: religionSect,
            marital_status: maritalStatus,
            first_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
            last_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
            dependents_count: numberField(0, "تعداد افراد تحت تکفل نمی‌تواند منفی باشد."),
            children_count: numberField(0, "تعداد فرزندان نمی‌تواند منفی باشد."),
            spouse_employment_status: spouseEmployment,
            spouse_job: text(100, "حداکثر ۱۰۰ کاراکتر."),
            military_status: militaryStatusSchema.optional(),
            id_number: idNumber(),
        })
        .superRefine((data, ctx) => {
            if (data.marital_status === MARITAL_MARRIED && !data.spouse_employment_status) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "وضعیت اشتغال همسر الزامی است.",
                    path: ["spouse_employment_status"],
                });
            }
            if (data.spouse_employment_status === SPOUSE_EMPLOYED && !data.spouse_job?.trim()) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "شغل همسر الزامی است.",
                    path: ["spouse_job"],
                });
            }
            if (data.gender === GENDER_MALE && !data.military_status) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: "وضعیت نظام وظیفه الزامی است.",
                    path: ["military_status"],
                });
            }
        });

    /**
     * Per-field schemas for use with TanStack Form validators.
     */
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
        id_number: idNumber(),
        blood_group: bloodGroup,
        birth_place: birthPlace,
        birth_certificate_number: birthCertificateNumber(),
        father_name: requiredText("نام پدر الزامی است.", 100),
        religion,
        religion_sect: religionSect,
        first_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
        last_name_en: text(100, "حداکثر ۱۰۰ کاراکتر."),
        dependents_count: numberField(0, "تعداد افراد تحت تکفل نمی‌تواند منفی باشد."),
        children_count: numberField(0, "تعداد فرزندان نمی‌تواند منفی باشد."),
        spouse_employment_status: spouseEmployment,
        spouse_job: requiredText("شغل همسر الزامی است.", 100),
        military_status: militaryStatusSchema,
    } as const;

    return { personalInfoFieldSchema, militaryStatusSchema, fieldSchemas };
}

export type PersonalInfoSchemas = ReturnType<typeof buildPersonalInfoSchemas>;

export type PersonalInfoFormData = z.infer<
    ReturnType<typeof buildPersonalInfoSchemas>["personalInfoFieldSchema"]
>;

/**
 * Default (draft) values for the personal info section, shared by the
 * questionnaire, CV, and employee profile forms.
 */
export function defaultPersonalInfo() {
    return {
        id_number: "",
        gender: "",
        birth_date: "",
        marital_status: "",
        first_name_en: "",
        last_name_en: "",
        birth_place: "",
        birth_certificate_number: "",
        father_name: "",
        religion: "",
        religion_sect: "",
        blood_group: "",
        dependents_count: null,
        children_count: null,
        spouse_employment_status: "",
        spouse_job: "",
        military_status: {
            status: "",
            organization: "",
            from: "",
            to: "",
            reason: "",
        },
    };
}

/**
 * Build the personal info section payload from the full form values. The
 * JSONB section is spread first so the top-level identity fields win: the
 * JSONB copy of first/last name is stale and must never overwrite what the
 * user just typed.
 */
export function toPersonalInfoPayload(values: {
    first_name?: string;
    last_name?: string;
    personal_info?: unknown;
}): Record<string, unknown> {
    const personalInfo =
        (values.personal_info as Record<string, unknown> | undefined) ?? {};
    return {
        ...personalInfo,
        first_name: values.first_name ?? "",
        last_name: values.last_name ?? "",
    };
}
