import { z } from "zod";

import { numberField, requiredText, text } from "@/lib/zod-primitives";

export const educationRecordSchema = z
    .object({
        degree: requiredText("مدرک الزامی است.", 50),
        field: requiredText("رشته تحصیلی الزامی است.", 100),
        institution: requiredText("دانشگاه الزامی است.", 100),
        location: text(100, "حداکثر ۱۰۰ کاراکتر."),
        from: requiredText("تاریخ شروع الزامی است."),
        to: requiredText("تاریخ پایان الزامی است."),
        thesis_title: text(255, "حداکثر ۲۵۵ کاراکتر."),
        graduation_date: requiredText("تاریخ فارغ‌التحصیلی الزامی است."),
        gpa: requiredText("معدل الزامی است.", 10),
    })
    .superRefine((data, ctx) => {
        if (data.from && data.to && data.to < data.from) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "تاریخ پایان نباید قبل از تاریخ شروع تحصیل باشد.",
                path: ["to"],
            });
        }
    });

export type EducationRecordFormData = z.infer<typeof educationRecordSchema>;

export const educationFieldSchema = z
    .object({
        education_records: z.array(educationRecordSchema).min(1, "حداقل یک سوابق تحصیلی الزامی است."),
        is_student: z.boolean().optional(),
        student_degree: text(50),
        student_field: text(100),
        student_university: text(100),
        student_country: text(100),
        student_city: text(100),
        student_semester: numberField(1, "ترم نمی‌تواند کمتر از ۱ باشد."),
        passed_units: numberField(0, "تعداد واحد نمی‌تواند منفی باشد."),
        remaining_units: numberField(0, "تعداد واحد نمی‌تواند منفی باشد."),
        student_gpa: text(10),
        study_start: text(),
        expected_graduation: text(),
        thesis_submitted: z.boolean().optional(),
        student_thesis_title: text(255),
        free_days_per_week: numberField(0, "روزهای آزاد نمی‌تواند منفی باشد.", 7),
        education_description: text(1000),
    })
    .superRefine((data, ctx) => {
        if (data.is_student) {
            const studentFields = [
                { key: "student_degree", msg: "مقطع تحصیلی الزامی است." },
                { key: "student_field", msg: "رشته تحصیلی الزامی است." },
                { key: "student_university", msg: "دانشگاه الزامی است." },
                { key: "student_country", msg: "کشور الزامی است." },
                { key: "student_city", msg: "شهر الزامی است." },
                { key: "student_gpa", msg: "معدل الزامی است." },
                { key: "study_start", msg: "تاریخ شروع تحصیل الزامی است." },
                { key: "expected_graduation", msg: "تاریخ انتظار فارغ‌التحصیلی الزامی است." },
            ] as const;
            for (const field of studentFields) {
                if (!data[field.key]) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        message: field.msg,
                        path: [field.key],
                    });
                }
            }
        }
        if (data.thesis_submitted && !data.student_thesis_title) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "عنوان پایان‌نامه الزامی است.",
                path: ["student_thesis_title"],
            });
        }
    });

export type EducationFormData = z.infer<typeof educationFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    education_records: z
        .array(z.object({}))
        .min(1, "حداقل یک سابقه تحصیلی الزامی است."),
    education_records_item: educationRecordSchema,
    student_degree: requiredText("مقطع تحصیلی الزامی است.", 50),
    student_field: requiredText("رشته تحصیلی الزامی است.", 100),
    student_university: requiredText("نام دانشگاه الزامی است.", 100),
    student_country: requiredText("کشور الزامی است.", 100),
    student_city: requiredText("شهر الزامی است.", 100),
    student_gpa: requiredText("معدل الزامی است.", 10),
    study_start: requiredText("تاریخ شروع تحصیل الزامی است."),
    expected_graduation: requiredText("تاریخ انتظار فارغ‌التحصیلی الزامی است."),
    student_thesis_title: requiredText("عنوان پایان‌نامه الزامی است.", 255),
} as const;

/**
 * Default (draft) values for the education section, shared by the
 * questionnaire, CV, and employee profile forms.
 */
export function defaultEducation() {
    return {
        education_records: [],
        is_student: false,
        student_degree: "",
        student_field: "",
        student_university: "",
        student_country: "",
        student_city: "",
        student_semester: null,
        passed_units: null,
        remaining_units: null,
        student_gpa: "",
        study_start: "",
        expected_graduation: "",
        thesis_submitted: false,
        student_thesis_title: "",
        free_days_per_week: null,
        education_description: "",
    };
}
