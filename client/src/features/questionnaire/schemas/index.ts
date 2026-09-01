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
    buildValidateSubmitData,
    type SubmitValidationResult,
} from "@/lib/submit-validation";
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

/**
 * Build a submit validator from fetched options. Pass `undefined` while the
 * options are still loading: the validator then reports no errors (so the
 * summary view doesn't flash spurious errors) and callers gate submission on
 * `optionsLoading` instead.
 */
export function buildSubmitValidator(
    options: SubmitOptions | undefined,
): (data: unknown) => SubmitValidationResult {
    return buildValidateSubmitData(
        options ? buildSubmitSchema(options) : undefined,
    );
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
