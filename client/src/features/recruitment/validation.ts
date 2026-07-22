import { z } from "zod";

export const personalInfoSchema = z.object({
    national_id: z.string().min(1, "کد ملی الزامی است."),
    gender: z.string().min(1, "جنسیت الزامی است."),
    birth_date: z.string().min(1, "تاریخ تولد الزامی است."),
    marital_status: z.string().min(1, "وضعیت تأهل الزامی است."),
});

export const educationRecordSchema = z.object({
    degree: z.string().optional(),
    field: z.string().optional(),
    institution: z.string().optional(),
    location: z.string().optional(),
    from: z.string().optional(),
    to: z.string().optional(),
    thesis_title: z.string().optional(),
    graduation_date: z.string().optional(),
    gpa: z.string().optional(),
});

export const educationSchema = z.object({
    education_records: z.array(educationRecordSchema).min(1, "حداقل یک سوابق تحصیلی الزامی است."),
});

export const workExperienceSchema = z.object({
    work_experiences: z.array(z.record(z.string(), z.unknown())).optional(),
});

export const skillsSchema = z.object({
    languages: z.array(z.record(z.string(), z.unknown())).optional(),
    software_skills: z.record(z.string(), z.unknown()).optional(),
    certificates: z.array(z.record(z.string(), z.unknown())).optional(),
    special_skills: z.array(z.string()).optional(),
});

export const trainingSchema = z.object({
    training_courses: z.array(z.record(z.string(), z.unknown())).optional(),
    researches: z.array(z.record(z.string(), z.unknown())).optional(),
    professional_memberships: z.string().optional(),
});

export const additionalInfoSchema = z.object({});

export const jobRequestSchema = z.object({
    employment_type: z.string().min(1, "نوع استخدام الزامی است."),
    accept_information: z.literal(true, { message: "باید اطلاعات را تأیید کنید." }),
});

export const submitSchema = z.object({
    personal_info: personalInfoSchema,
    education: educationSchema,
    work_experience: workExperienceSchema,
    skills: skillsSchema,
    training: trainingSchema,
    additional_info: additionalInfoSchema,
    job_request: jobRequestSchema,
});

export type SubmitFormData = z.infer<typeof submitSchema>;

export function validateSubmitData(data: unknown): { success: boolean; errors: string[] } {
    const result = submitSchema.safeParse(data);
    if (result.success) {
        return { success: true, errors: [] };
    }
    const errors = result.error.issues.map((issue) => issue.message);
    return { success: false, errors };
}
