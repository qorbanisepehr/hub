import { z } from "zod";

import {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
} from "./personal-info.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { educationFieldSchema } from "@/features/questionnaire/schemas/education.schema";
import { workExperienceFieldSchema } from "@/features/questionnaire/schemas/work-experience.schema";
import { skillsFieldSchema } from "@/features/questionnaire/schemas/skills.schema";
import { trainingFieldSchema } from "@/features/questionnaire/schemas/training.schema";
import { mobile, optionalEmail } from "@/lib/field-rules";
import {
    buildValidateSubmitData,
    type SubmitValidationResult,
} from "@/lib/submit-validation";
import { requiredText } from "@/lib/zod-primitives";

export type SubmitOptions = {
    personal_info: PersonalInfoOptions;
};

export function buildSubmitSchema(options: SubmitOptions) {
    const { personalInfoFieldSchema } = buildPersonalInfoSchemas(options.personal_info);

    return z.object({
        first_name: requiredText("نام الزامی است.", 100),
        last_name: requiredText("نام خانوادگی الزامی است.", 100),
        // Email stays optional on a CV, but once filled it must be OTP-verified.
        email: optionalEmail(),
        mobile: mobile(),
        personal_info: personalInfoFieldSchema,
        contact_info: contactInfoFieldSchema,
        education: educationFieldSchema,
        work_experience: workExperienceFieldSchema,
        skills: skillsFieldSchema,
        training: trainingFieldSchema,
        additional_info: additionalInfoFieldSchema,
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

export {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
    type PersonalInfoSchemas,
} from "./personal-info.schema";
export {
    contactInfoFieldSchema,
    fieldSchemas as contactInfoFieldSchemas,
    addressSchema,
} from "./contact-info.schema";
export {
    additionalInfoFieldSchema,
    fieldSchemas as additionalInfoFieldSchemas,
    referenceSchema,
} from "./additional-info.schema";
