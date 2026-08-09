import { z } from "zod";

import { buildPersonalInfoSchemas, type PersonalInfoOptions } from "./personal-info.schema";
import { buildJobRequestSchemas, type JobRequestOptions } from "./job-request.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { educationFieldSchema } from "./education.schema";
import { workExperienceFieldSchema } from "./work-experience.schema";
import { skillsFieldSchema } from "./skills.schema";
import { trainingFieldSchema } from "./training.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { email, mobile } from "@/lib/field-rules";
import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";
import { requiredText } from "@/lib/zod-primitives";

export type SubmitOptions = {
    personal_info: PersonalInfoOptions;
    job_request: JobRequestOptions;
};

export function buildSubmitSchema(options: SubmitOptions) {
    const { personalInfoFieldSchema } = buildPersonalInfoSchemas(options.personal_info);
    const { jobRequestFieldSchema } = buildJobRequestSchemas(options.job_request);

    return z.object({
        first_name: requiredText("نام الزامی است.", 100),
        last_name: requiredText("نام خانوادگی الزامی است.", 100),
        email: email(),
        mobile: mobile(),
        personal_info: personalInfoFieldSchema,
        contact_info: contactInfoFieldSchema,
        education: educationFieldSchema,
        work_experience: workExperienceFieldSchema,
        skills: skillsFieldSchema,
        training: trainingFieldSchema,
        additional_info: additionalInfoFieldSchema,
        job_request: jobRequestFieldSchema,
    });
}

export type SubmitFormData = z.infer<ReturnType<typeof buildSubmitSchema>>;

export type SubmitValidationResult = {
    success: boolean;
    errors: string[];
    fieldErrors: FieldErrors;
};

/**
 * Build a submit validator from fetched options. Pass `undefined` while the
 * options are still loading: the validator then reports no errors (so the
 * summary view doesn't flash spurious errors) and callers gate submission on
 * `optionsLoading` instead.
 */
export function buildValidateSubmitData(
    options: SubmitOptions | undefined,
): (data: unknown) => SubmitValidationResult {
    const schema = options ? buildSubmitSchema(options) : undefined;

    return (data) => {
        if (!schema) {
            return { success: false, errors: [], fieldErrors: {} };
        }
        const result = schema.safeParse(data);
        if (result.success) {
            return { success: true, errors: [], fieldErrors: {} };
        }
        return {
            success: false,
            errors: result.error.issues.map((issue) => zodIssueMessage(issue)),
            fieldErrors: zodFieldErrors(result.error),
        };
    };
}

// Re-export section schemas and builders
export {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
    type PersonalInfoSchemas,
} from "./personal-info.schema";
export {
    buildJobRequestSchemas,
    type JobRequestOptions,
    type JobRequestSchemas,
} from "./job-request.schema";
export { contactInfoFieldSchema, fieldSchemas as contactInfoFieldSchemas } from "./contact-info.schema";
export { educationFieldSchema, fieldSchemas as educationFieldSchemas } from "./education.schema";
export { workExperienceFieldSchema, fieldSchemas as workExperienceFieldSchemas } from "./work-experience.schema";
export { skillsFieldSchema, fieldSchemas as skillsFieldSchemas } from "./skills.schema";
export { trainingFieldSchema, fieldSchemas as trainingFieldSchemas } from "./training.schema";
export { additionalInfoFieldSchema, fieldSchemas as additionalInfoFieldSchemas } from "./additional-info.schema";

// Re-export individual record schemas for nested form usage
export { educationRecordSchema } from "./education.schema";
export { workExperienceRecordSchema } from "./work-experience.schema";
export { languageSkillSchema, softwareSkillSchema, certificateSchema } from "./skills.schema";
export { trainingCourseSchema, researchSchema } from "./training.schema";
export { referenceSchema } from "./additional-info.schema";
export { addressSchema } from "./contact-info.schema";
