import { z } from "zod";

import { personalInfoFieldSchema } from "./personal-info.schema";
import { educationFieldSchema } from "./education.schema";
import { workExperienceFieldSchema } from "./work-experience.schema";
import { skillsFieldSchema } from "./skills.schema";
import { trainingFieldSchema } from "./training.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { jobRequestFieldSchema } from "./job-request.schema";

export const submitSchema = z.object({
    first_name: z.string().min(1, "نام الزامی است.").max(100),
    last_name: z.string().min(1, "نام خانوادگی الزامی است.").max(100),
    email: z.string().min(1, "ایمیل الزامی است.").email("فرمت ایمیل نادرست است.").max(255),
    mobile: z.string().min(1, "شماره موبایل الزامی است.").max(15),
    personal_info: personalInfoFieldSchema,
    education: educationFieldSchema,
    work_experience: workExperienceFieldSchema,
    skills: skillsFieldSchema,
    training: trainingFieldSchema,
    additional_info: additionalInfoFieldSchema,
    job_request: jobRequestFieldSchema,
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

// Re-export section schemas and field schemas
export { personalInfoFieldSchema, fieldSchemas as personalInfoFieldSchemas } from "./personal-info.schema";
export { educationFieldSchema, fieldSchemas as educationFieldSchemas } from "./education.schema";
export { workExperienceFieldSchema, fieldSchemas as workExperienceFieldSchemas } from "./work-experience.schema";
export { skillsFieldSchema, fieldSchemas as skillsFieldSchemas } from "./skills.schema";
export { trainingFieldSchema, fieldSchemas as trainingFieldSchemas } from "./training.schema";
export { additionalInfoFieldSchema, fieldSchemas as additionalInfoFieldSchemas } from "./additional-info.schema";
export { jobRequestFieldSchema, fieldSchemas as jobRequestFieldSchemas } from "./job-request.schema";

// Re-export individual record schemas for nested form usage
export { educationRecordSchema } from "./education.schema";
export { workExperienceRecordSchema } from "./work-experience.schema";
export { languageSkillSchema, softwareSkillSchema, certificateSchema } from "./skills.schema";
export { trainingCourseSchema, researchSchema } from "./training.schema";
export { referenceSchema } from "./additional-info.schema";
export { militaryStatusSchema } from "./personal-info.schema";
