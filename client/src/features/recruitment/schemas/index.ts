import { z } from "zod";

import { personalInfoFieldSchema } from "./personal-info.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { educationFieldSchema } from "./education.schema";
import { workExperienceFieldSchema } from "./work-experience.schema";
import { skillsFieldSchema } from "./skills.schema";
import { trainingFieldSchema } from "./training.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { jobRequestFieldSchema } from "./job-request.schema";
import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";
import { requiredText } from "@/lib/zod-primitives";

export const submitSchema = z.object({
    first_name: requiredText("نام الزامی است.", 100),
    last_name: requiredText("نام خانوادگی الزامی است.", 100),
    email: requiredText("ایمیل الزامی است.", 255).refine(
        (v) => z.string().email().safeParse(v).success,
        "فرمت ایمیل نادرست است.",
    ),
    mobile: requiredText("شماره موبایل الزامی است.", 15),
    personal_info: personalInfoFieldSchema,
    contact_info: contactInfoFieldSchema,
    education: educationFieldSchema,
    work_experience: workExperienceFieldSchema,
    skills: skillsFieldSchema,
    training: trainingFieldSchema,
    additional_info: additionalInfoFieldSchema,
    job_request: jobRequestFieldSchema,
});

export type SubmitFormData = z.infer<typeof submitSchema>;

export function validateSubmitData(
    data: unknown,
): { success: boolean; errors: string[]; fieldErrors: FieldErrors } {
    const result = submitSchema.safeParse(data);
    if (result.success) {
        return { success: true, errors: [], fieldErrors: {} };
    }
    return {
        success: false,
        errors: result.error.issues.map((issue) => zodIssueMessage(issue)),
        fieldErrors: zodFieldErrors(result.error),
    };
}

// Re-export section schemas and field schemas
export { personalInfoFieldSchema, fieldSchemas as personalInfoFieldSchemas } from "./personal-info.schema";
export { contactInfoFieldSchema, fieldSchemas as contactInfoFieldSchemas } from "./contact-info.schema";
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
export { addressSchema } from "./contact-info.schema";
export { militaryStatusSchema } from "./personal-info.schema";
