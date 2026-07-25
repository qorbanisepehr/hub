import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const educationRecordSchema = z.object({
    degree: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    field: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    institution: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    location: z.string().max(100).optional(),
    from: requiredString,
    to: requiredString,
    thesis_title: z.string().max(255).optional().nullable(),
    graduation_date: requiredString,
    gpa: requiredString.max(10, "حداکثر ۱۰ کاراکتر."),
});

export type EducationRecordFormData = z.infer<typeof educationRecordSchema>;

export const educationFieldSchema = z
    .object({
        education_records: z.array(educationRecordSchema).min(1, "حداقل یک سوابق تحصیلی الزامی است."),
        is_student: z.boolean().optional(),
        student_degree: z.string().max(50).optional(),
        student_field: z.string().max(100).optional(),
        student_university: z.string().max(100).optional(),
        student_country: z.string().max(100).optional(),
        student_city: z.string().max(100).optional(),
        student_semester: z.number().min(1).nullable().optional(),
        passed_units: z.number().min(0).nullable().optional(),
        remaining_units: z.number().min(0).nullable().optional(),
        student_gpa: z.string().max(10).optional(),
        study_start: z.string().optional(),
        expected_graduation: z.string().optional(),
        thesis_submitted: z.boolean().optional(),
        student_thesis_title: z.string().max(255).optional(),
        free_days_per_week: z.number().min(0).max(7).nullable().optional(),
        education_description: z.string().max(1000).optional(),
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
        .min(1, "حداقل یک سوابق تحصیلی الزامی است."),
    education_records_item: educationRecordSchema,
    student_degree: requiredString,
    student_field: requiredString,
    student_university: requiredString,
    student_country: requiredString,
    student_city: requiredString,
    student_gpa: requiredString,
    study_start: requiredString,
    expected_graduation: requiredString,
    student_thesis_title: requiredString,
} as const;
